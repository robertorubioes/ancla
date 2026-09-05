<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Enums\TemplateFieldType;
use App\Enums\UserRole;
use App\Http\Middleware\IdentifyTenant;
use App\Livewire\Template\TemplateFill;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateField;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Template\TemplateVersionService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

/**
 * Vista previa del documento mientras se rellena.
 *
 * Sirve para ver como queda antes de enviarlo, que es donde se detecta que
 * un importe no cabe en su caja. El PDF no se persiste: vive en cache y
 * caduca solo.
 */
class TemplatePreviewTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private DocumentTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($this->tenant);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
        ]);

        $this->template = $this->publishedTemplate();
    }

    private function publishedTemplate(float $anchoCaja = 80.0): DocumentTemplate
    {
        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'CONTRATO BASE', 0, 1);

        $path = 'documents/test';
        $stored = Str::uuid().'.pdf';
        Storage::disk('local')->put("{$path}/{$stored}", $pdf->Output('S'));

        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'status' => Document::STATUS_READY,
            'storage_disk' => 'local',
            'storage_path' => "{$path}/{$stored}",
            'stored_filename' => $stored,
            'is_encrypted' => false,
        ]);

        $versions = app(TemplateVersionService::class);
        $template = $versions->createFromDocument($document, $this->admin, 'Contrato');

        DocumentTemplateField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $template->versions()->first()->id,
            'key' => 'arrendatario',
            'label' => 'Arrendatario',
            'type' => TemplateFieldType::TEXT,
            'width' => $anchoCaja,
            'y' => 40.0,
        ]);

        $versions->publish($template, $this->admin);

        return $template->refresh();
    }

    private function fill(): Testable
    {
        return Livewire::actingAs($this->admin)
            ->test(TemplateFill::class, ['template' => $this->template]);
    }

    public function test_la_vista_previa_muestra_los_valores_escritos(): void
    {
        $component = $this->fill()
            ->set('values.arrendatario', 'Ana Ruiz')
            ->call('preview')
            ->assertSet('previewError', '');

        $key = $component->get('previewKey');
        $this->assertNotNull($key);

        $pdf = $this->actingAs($this->admin)
            ->withoutMiddleware(IdentifyTenant::class)
            ->get(route('templates.preview', ['key' => $key]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->getContent();

        $texto = (new Parser)->parseContent((string) $pdf)->getText();

        $this->assertStringContainsString('Ana Ruiz', $texto);
        $this->assertStringContainsString('CONTRATO BASE', $texto);
    }

    public function test_funciona_con_el_formulario_a_medias(): void
    {
        // La gracia es ver el documento MIENTRAS se rellena.
        $this->fill()
            ->call('preview')
            ->assertSet('previewError', '')
            ->assertNotSet('previewKey', null);
    }

    public function test_avisa_cuando_un_valor_no_cabe_en_su_caja(): void
    {
        // Es el fallo que mas duele en un contrato real, y para esto sirve
        // sobre todo la vista previa.
        $this->template = $this->publishedTemplate(anchoCaja: 10.0);

        $this->fill()
            ->set('values.arrendatario', 'Un nombre desmesuradamente largo que no entra en diez milimetros')
            ->call('preview')
            ->assertSet('previewKey', null)
            ->assertSee('no cabe en el espacio reservado');
    }

    public function test_el_documento_se_ve_desde_el_primer_momento(): void
    {
        // Sin tener que pedirlo: asi se entiende que es lo que se rellena.
        $this->assertNotNull($this->fill()->get('previewKey'));
    }

    public function test_cambiar_un_valor_rehace_la_vista_previa(): void
    {
        // La gracia es ver el documento completandose mientras se escribe.
        $component = $this->fill()->set('values.arrendatario', 'Ana');

        $primera = $component->get('previewKey');
        $this->assertNotNull($primera);

        $component->set('values.arrendatario', 'Luis');

        $segunda = $component->get('previewKey');

        $this->assertNotNull($segunda);
        $this->assertNotSame($primera, $segunda, 'Cada cambio produce una vista previa nueva.');

        $pdf = $this->actingAs($this->admin)
            ->withoutMiddleware(IdentifyTenant::class)
            ->get(route('templates.preview', ['key' => $segunda]))
            ->getContent();

        $texto = (new Parser)->parseContent((string) $pdf)->getText();

        $this->assertStringContainsString('Luis', $texto);
        $this->assertStringNotContainsString('Ana', $texto);
    }

    public function test_la_vista_previa_no_persiste_ningun_documento(): void
    {
        $documentosAntes = Document::count();

        $this->fill()
            ->set('values.arrendatario', 'Ana')
            ->call('preview');

        $this->assertSame($documentosAntes, Document::count(), 'Una vista previa no crea documentos.');
        $this->assertSame(0, SigningProcess::count());
    }

    public function test_otro_usuario_no_puede_ver_una_vista_previa_ajena(): void
    {
        $key = $this->fill()
            ->set('values.arrendatario', 'Ana')
            ->call('preview')
            ->get('previewKey');

        $otro = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
        ]);

        $this->actingAs($otro)
            ->withoutMiddleware(IdentifyTenant::class)
            ->get(route('templates.preview', ['key' => $key]))
            ->assertForbidden();
    }

    public function test_una_clave_inventada_no_devuelve_nada(): void
    {
        $this->actingAs($this->admin)
            ->withoutMiddleware(IdentifyTenant::class)
            ->get(route('templates.preview', ['key' => Str::random(40)]))
            ->assertNotFound();
    }
}
