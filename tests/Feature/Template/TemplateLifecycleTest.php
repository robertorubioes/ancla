<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateField;
use App\Models\DocumentTemplateSigner;
use App\Models\DocumentTemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Template\TemplateException;
use App\Services\Template\TemplateVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un documento subido es una firma puntual. Convertirlo en plantilla y
 * habilitarla son dos decisiones explicitas y separadas.
 */
class TemplateLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $author;

    private Document $document;

    private TemplateVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->author = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->author->id,
            'status' => Document::STATUS_READY,
        ]);

        $this->service = app(TemplateVersionService::class);
    }

    private function withSchema(DocumentTemplateVersion $version): void
    {
        DocumentTemplateField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $version->id,
            'key' => 'nombre',
        ]);

        DocumentTemplateSigner::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $version->id,
            'role_key' => 'firmante',
        ]);
    }

    public function test_convertir_un_documento_crea_una_plantilla_en_borrador(): void
    {
        $template = $this->service->createFromDocument(
            $this->document,
            $this->author,
            'Contrato de alquiler',
        );

        $this->assertSame(DocumentTemplate::STATUS_DRAFT, $template->status);
        $this->assertNull($template->current_version_id, 'Una plantilla recien creada no es utilizable.');
        $this->assertFalse($template->isUsable());
        $this->assertCount(1, $template->versions);
        $this->assertSame(1, $template->versions->first()->version);
        $this->assertSame($this->document->id, $template->versions->first()->document_id);
    }

    public function test_no_se_convierte_un_documento_que_no_esta_listo(): void
    {
        $this->document->update(['status' => Document::STATUS_PENDING]);

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('todavia no esta listo');

        $this->service->createFromDocument($this->document, $this->author, 'X');
    }

    public function test_no_se_convierte_un_documento_de_otro_tenant(): void
    {
        $ajeno = Document::factory()->create([
            'tenant_id' => Tenant::factory()->create()->id,
            'status' => Document::STATUS_READY,
        ]);

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('no pertenece a tu organizacion');

        $this->service->createFromDocument($ajeno, $this->author, 'X');
    }

    public function test_habilitar_publica_la_version_y_activa_la_plantilla(): void
    {
        $template = $this->service->createFromDocument($this->document, $this->author, 'Contrato');
        $draft = $template->versions()->first();
        $this->withSchema($draft);

        $published = $this->service->publish($template, $this->author);

        $template->refresh();

        $this->assertNotNull($published->published_at);
        $this->assertSame(DocumentTemplate::STATUS_ACTIVE, $template->status);
        $this->assertSame($published->id, $template->current_version_id);
        $this->assertTrue($template->isUsable());
    }

    public function test_se_habilita_sin_firmantes_previstos(): void
    {
        // Una plantilla no es un proceso de firma: es un documento con
        // variables. Los roles son opcionales, y quien firma se decide al
        // usarla.
        $template = $this->service->createFromDocument($this->document, $this->author, 'Contrato');
        DocumentTemplateField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $template->versions()->first()->id,
        ]);

        $this->service->publish($template, $this->author);

        $this->assertTrue($template->refresh()->isUsable());
    }

    public function test_no_se_habilita_sin_campos(): void
    {
        // Sin campos variables una plantilla no aporta nada sobre subir el PDF.
        $template = $this->service->createFromDocument($this->document, $this->author, 'Contrato');
        DocumentTemplateSigner::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $template->versions()->first()->id,
        ]);

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('ningun campo');

        $this->service->publish($template, $this->author);
    }

    public function test_editar_una_plantilla_publicada_abre_una_version_nueva(): void
    {
        $template = $this->service->createFromDocument($this->document, $this->author, 'Contrato');
        $v1 = $template->versions()->first();
        $this->withSchema($v1);
        $this->service->publish($template, $this->author);

        $v2 = $this->service->openNewDraft($template->refresh(), $this->author);

        $this->assertSame(2, $v2->version);
        $this->assertNull($v2->published_at);
        $this->assertNotNull($v1->refresh()->published_at, 'La version publicada no se toca.');
        $this->assertSame($v1->id, $template->refresh()->current_version_id, 'La vigente sigue siendo la 1 hasta publicar la 2.');
    }

    public function test_la_version_nueva_hereda_el_esquema(): void
    {
        $template = $this->service->createFromDocument($this->document, $this->author, 'Contrato');
        $v1 = $template->versions()->first();
        $this->withSchema($v1);
        $this->service->publish($template, $this->author);

        $v2 = $this->service->openNewDraft($template->refresh(), $this->author);

        $this->assertSame(['nombre'], $v2->fields()->pluck('key')->all());
        $this->assertSame(['firmante'], $v2->signerRoles()->pluck('role_key')->all());
    }

    public function test_abrir_un_borrador_dos_veces_devuelve_el_mismo(): void
    {
        $template = $this->service->createFromDocument($this->document, $this->author, 'Contrato');
        $this->withSchema($template->versions()->first());
        $this->service->publish($template, $this->author);

        $a = $this->service->openNewDraft($template->refresh(), $this->author);
        $b = $this->service->openNewDraft($template->refresh(), $this->author);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(2, $template->refresh()->versions()->count());
    }

    public function test_archivar_no_borra_la_plantilla(): void
    {
        // Los procesos ya emitidos apuntan a sus versiones: no se puede borrar.
        $template = $this->service->createFromDocument($this->document, $this->author, 'Contrato');
        $this->withSchema($template->versions()->first());
        $this->service->publish($template, $this->author);

        $this->service->archive($template->refresh());

        $template->refresh();

        $this->assertSame(DocumentTemplate::STATUS_ARCHIVED, $template->status);
        $this->assertFalse($template->isUsable());
        $this->assertDatabaseHas('document_templates', ['id' => $template->id]);
        $this->assertNotNull($template->current_version_id, 'La version sigue ahi para reconstruir procesos.');
    }

    public function test_queda_registrado_en_el_audit_trail(): void
    {
        $template = $this->service->createFromDocument($this->document, $this->author, 'Contrato');
        $this->withSchema($template->versions()->first());
        $this->service->publish($template, $this->author);

        $this->assertDatabaseHas('audit_trail_entries', [
            'auditable_type' => DocumentTemplate::class,
            'auditable_id' => $template->id,
            'event_type' => 'template.created',
        ]);

        $this->assertDatabaseHas('audit_trail_entries', [
            'auditable_type' => DocumentTemplate::class,
            'auditable_id' => $template->id,
            'event_type' => 'template.published',
        ]);
    }
}
