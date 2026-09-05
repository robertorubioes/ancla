<?php

namespace App\Services\Template;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateSigner;
use App\Models\DocumentTemplateVersion;
use App\Models\User;
use App\Services\Evidence\AuditTrailService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ciclo de vida de una plantilla.
 *
 * Un documento subido es, por defecto, una firma puntual. Solo se convierte
 * en plantilla si alguien lo decide explicitamente, y solo puede usarse
 * cuando ademas se habilita: mientras esta en borrador se puede editar, pero
 * no genera procesos.
 *
 *   documento subido
 *        |
 *        v  convertir en plantilla        (decision explicita del usuario)
 *   plantilla en borrador  ---- editor ---->  campos y firmantes definidos
 *        |
 *        v  habilitar                     (segunda decision explicita)
 *   plantilla activa, con version publicada
 *
 * Publicar congela la version. Los cambios posteriores abren una version
 * nueva, de modo que los procesos ya emitidos siguen apuntando a la que los
 * genero.
 *
 * @see docs/architecture/adr-011-plantillas-y-api-de-cumplimentacion.md
 */
class TemplateVersionService
{
    public function __construct(
        private readonly AuditTrailService $auditTrail,
    ) {}

    /**
     * Convierte un documento subido en una plantilla en borrador.
     *
     * @throws TemplateException
     */
    public function createFromDocument(
        Document $document,
        User $author,
        string $name,
        ?string $description = null,
    ): DocumentTemplate {
        if (! $document->isReady()) {
            throw TemplateException::documentNotReady();
        }

        if ($document->tenant_id !== $author->tenant_id) {
            throw TemplateException::documentFromAnotherTenant();
        }

        return DB::transaction(function () use ($document, $author, $name, $description): DocumentTemplate {
            $template = DocumentTemplate::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $author->tenant_id,
                'name' => $name,
                'description' => $description,
                'status' => DocumentTemplate::STATUS_DRAFT,
                'created_by' => $author->id,
            ]);

            $this->createDraftVersion($template, $document, $author);

            $this->auditTrail->record($template, 'template.created', [
                'template_uuid' => $template->uuid,
                'document_id' => $document->id,
                'name' => $name,
            ]);

            return $template->refresh();
        });
    }

    /**
     * Abre una version nueva en borrador, copiando el esquema de la vigente.
     *
     * Se usa al querer cambiar una plantilla ya habilitada: la publicada no
     * se toca.
     *
     * @throws TemplateException
     */
    public function openNewDraft(DocumentTemplate $template, User $author): DocumentTemplateVersion
    {
        $existingDraft = $template->versions()->whereNull('published_at')->first();

        if ($existingDraft !== null) {
            return $existingDraft;
        }

        $current = $template->currentVersion;

        if ($current === null) {
            throw TemplateException::nothingToCopy();
        }

        return DB::transaction(function () use ($template, $current, $author): DocumentTemplateVersion {
            $draft = $this->createDraftVersion($template, $current->document, $author);

            foreach ($current->fields as $field) {
                $copy = $field->replicate(['uuid', 'document_template_version_id']);
                $copy->uuid = (string) Str::uuid();
                $copy->document_template_version_id = $draft->id;
                $copy->save();
            }

            foreach ($current->signerRoles as $role) {
                $copy = $role->replicate(['uuid', 'document_template_version_id']);
                $copy->uuid = (string) Str::uuid();
                $copy->document_template_version_id = $draft->id;
                $copy->save();
            }

            return $draft->refresh();
        });
    }

    /**
     * Habilita la plantilla: publica su borrador y lo deja como vigente.
     *
     * Es la decision que convierte una plantilla en utilizable.
     *
     * NO se exigen firmantes previstos. Una plantilla no es un proceso de
     * firma: es un documento con variables. Quien firma se decide al usarla.
     * Los roles son opcionales y sirven para dos cosas, cuando se conocen de
     * antemano: fijar donde firma cada uno, y rellenar solos los campos
     * signer_name y signer_email.
     *
     * @throws TemplateException
     */
    public function publish(DocumentTemplate $template, User $author): DocumentTemplateVersion
    {
        $draft = $template->versions()->whereNull('published_at')->orderByDesc('version')->first();

        if ($draft === null) {
            throw TemplateException::noDraftToPublish();
        }

        if ($draft->fields()->count() === 0) {
            throw TemplateException::needsFields();
        }

        return DB::transaction(function () use ($template, $draft): DocumentTemplateVersion {
            $draft->update(['published_at' => now()]);

            $template->update([
                'current_version_id' => $draft->id,
                'status' => DocumentTemplate::STATUS_ACTIVE,
            ]);

            $this->auditTrail->record($template, 'template.published', [
                'template_uuid' => $template->uuid,
                'version' => $draft->version,
                'fields' => $draft->fields()->count(),
                'signer_roles' => $draft->signerRoles()->count(),
            ]);

            return $draft->refresh();
        });
    }

    /**
     * Retira una plantilla de circulacion sin borrarla.
     *
     * No se elimina: los procesos ya emitidos apuntan a sus versiones y
     * tienen que poder reconstruirse.
     */
    public function archive(DocumentTemplate $template): DocumentTemplate
    {
        $template->update(['status' => DocumentTemplate::STATUS_ARCHIVED]);

        $this->auditTrail->record($template, 'template.archived', [
            'template_uuid' => $template->uuid,
        ]);

        return $template->refresh();
    }

    private function createDraftVersion(
        DocumentTemplate $template,
        Document $document,
        User $author,
    ): DocumentTemplateVersion {
        $next = (int) $template->versions()->max('version') + 1;

        return DocumentTemplateVersion::create([
            'uuid' => (string) Str::uuid(),
            'tenant_id' => $template->tenant_id,
            'document_template_id' => $template->id,
            'version' => $next,
            'document_id' => $document->id,
            'created_by' => $author->id,
            'published_at' => null,
        ]);
    }

    /**
     * Campos y firmantes de una version, listos para el formulario.
     *
     * @return array{schema: TemplateSchema, roles: Collection<int, DocumentTemplateSigner>}
     */
    public function describe(DocumentTemplateVersion $version): array
    {
        return [
            'schema' => TemplateSchema::for($version),
            'roles' => $version->signerRoles()->get(),
        ];
    }
}
