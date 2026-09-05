<?php

namespace App\Services\Template;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Models\User;
use App\Services\Evidence\AuditTrailService;
use App\Services\Evidence\HashingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Genera un proceso de firma a partir de una plantilla rellenada.
 *
 * El PDF resultante entra en el sistema como un Document normal: a partir de
 * ahi no hay nada especial. Firma PAdES, evidencias, verificacion publica y
 * dossier funcionan sin enterarse de que vino de una plantilla.
 *
 * Lo unico que se conserva es la referencia a la version usada y los valores
 * con que se relleno, para poder demostrar anos despues que produjo el
 * documento firmado.
 *
 * @see docs/architecture/adr-011-plantillas-y-api-de-cumplimentacion.md
 */
class TemplateProcessService
{
    public function __construct(
        private readonly TemplateRenderService $renderer,
        private readonly HashingService $hashing,
        private readonly AuditTrailService $auditTrail,
    ) {}

    /**
     * @param  array<string, mixed>  $values  Sin validar: se validan aqui
     * @param  list<array{name: string, email: string, phone?: string|null, role?: string|null}>  $signers
     *
     * @throws TemplateException
     * @throws TemplateRenderException
     * @throws ValidationException
     */
    public function createProcess(
        DocumentTemplate $template,
        User $author,
        array $values,
        array $signers,
        ?string $customMessage = null,
        ?string $deadlineAt = null,
        string $signatureOrder = SigningProcess::ORDER_PARALLEL,
    ): SigningProcess {
        if (! $template->isUsable()) {
            throw TemplateException::notUsable();
        }

        /** @var DocumentTemplateVersion $version */
        $version = $template->currentVersion;

        // La misma validacion que usara el endpoint de la API: si divergen,
        // la plataforma acepta por un lado lo que rechaza por el otro.
        $clean = TemplateSchema::for($version)->validate($values);

        $this->assertSignersMatchTemplate($version, $signers);

        $pdf = $this->renderer->render($version, $clean, $this->signersByRole($signers));

        return DB::transaction(function () use (
            $template, $version, $author, $clean, $signers, $pdf,
            $customMessage, $deadlineAt, $signatureOrder
        ): SigningProcess {
            $document = $this->storeGeneratedDocument($template, $version, $author, $pdf);

            $process = SigningProcess::create([
                'uuid' => (string) Str::uuid(),
                'tenant_id' => $author->tenant_id,
                'document_id' => $document->id,
                'document_template_version_id' => $version->id,
                'template_values' => $clean,
                'created_by' => $author->id,
                'status' => SigningProcess::STATUS_DRAFT,
                'signature_order' => $signatureOrder,
                'custom_message' => $customMessage,
                'deadline_at' => $deadlineAt !== null ? now()->parse($deadlineAt) : null,
            ]);

            foreach ($signers as $order => $data) {
                Signer::create([
                    'uuid' => (string) Str::uuid(),
                    'signing_process_id' => $process->id,
                    'name' => trim($data['name']),
                    'email' => trim(strtolower($data['email'])),
                    'phone' => ! empty($data['phone']) ? trim((string) $data['phone']) : null,
                    'order' => $order,
                    'status' => Signer::STATUS_PENDING,
                    'token' => Str::random(32),
                ]);
            }

            $this->auditTrail->record($process, 'signing_process.created_from_template', [
                'process_uuid' => $process->uuid,
                'template_uuid' => $template->uuid,
                'template_version' => $version->version,
                'document_id' => $document->id,
                'signers_count' => count($signers),
            ]);

            return $process->refresh();
        });
    }

    /**
     * Guarda el PDF generado como un documento del sistema.
     */
    private function storeGeneratedDocument(
        DocumentTemplate $template,
        DocumentTemplateVersion $version,
        User $author,
        string $pdf,
    ): Document {
        $uuid = (string) Str::uuid();
        $disk = config('documents.storage_disk', 'local');
        $filename = $uuid.'.pdf';
        // storage_path guarda la ruta COMPLETA, incluido el nombre: es la
        // convencion de DocumentUploadService::encryptAndStore().
        $path = 'documents/'.now()->format('Y/m').'/'.$filename;

        Storage::disk($disk)->put($path, $pdf);

        return Document::create([
            'uuid' => $uuid,
            'tenant_id' => $author->tenant_id,
            'user_id' => $author->id,
            'original_filename' => $this->filenameFor($template),
            'original_extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => strlen($pdf),
            'sha256_hash' => $this->hashing->hashString($pdf),
            'storage_disk' => $disk,
            'storage_path' => $path,
            'stored_filename' => $filename,
            'is_encrypted' => false,
            'status' => Document::STATUS_READY,
            'pdf_metadata' => [
                'generated_from_template' => $template->uuid,
                'template_version' => $version->version,
            ],
        ]);
    }

    private function filenameFor(DocumentTemplate $template): string
    {
        $slug = Str::slug($template->name) ?: 'documento';

        return "{$slug}-".now()->format('Ymd-His').'.pdf';
    }

    /**
     * Comprueba los firmantes recibidos.
     *
     * Una plantilla puede fijar roles ("arrendador", "arrendatario") o no
     * fijar ninguno. Si los fija, hay que cubrirlos todos. Si no, vale
     * cualquier lista, como en un proceso de firma normal: quien firma se
     * decide al usar la plantilla, no al definirla.
     *
     * @param  list<array<string, mixed>>  $signers
     *
     * @throws TemplateException
     */
    private function assertSignersMatchTemplate(DocumentTemplateVersion $version, array $signers): void
    {
        foreach ($signers as $data) {
            if (blank($data['name'] ?? null) || blank($data['email'] ?? null)) {
                throw TemplateException::incompleteSigner();
            }
        }

        $roles = $version->signerRoles;

        if ($roles->isEmpty()) {
            if ($signers === []) {
                throw TemplateException::needsAtLeastOneSigner();
            }

            return;
        }

        $asignados = array_filter(array_column($signers, 'role'));

        foreach ($roles as $role) {
            if (! in_array($role->role_key, $asignados, true)) {
                throw TemplateException::missingSigner($role->label);
            }
        }
    }

    /**
     * Firmantes indexados por rol, para los campos calculados y la posicion
     * de firma. Los que no traen rol no aparecen aqui.
     *
     * @param  list<array<string, mixed>>  $signers
     * @return array<string, array<string, mixed>>
     */
    private function signersByRole(array $signers): array
    {
        $byRole = [];

        foreach ($signers as $data) {
            $role = $data['role'] ?? null;

            if (is_string($role) && $role !== '') {
                $byRole[$role] = $data;
            }
        }

        return $byRole;
    }
}
