<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Enums\TemplateFieldType;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateField;
use App\Models\DocumentTemplateSigner;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Template\TemplateException;
use App\Services\Template\TemplateProcessService;
use App\Services\Template\TemplateVersionService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class TemplateProcessTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $author;

    private DocumentTemplate $template;

    private TemplateProcessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenant = Tenant::factory()->create();

        // El cifrado en reposo usa claves por tenant, de modo que necesita el
        // contexto que en una peticion real inyecta el middleware.
        app(TenantContext::class)->set($this->tenant);

        $this->author = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->service = app(TemplateProcessService::class);
        $this->template = $this->publishedTemplate();
    }

    private function realPdf(): string
    {
        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'CONTRATO', 0, 1);

        return $pdf->Output('S');
    }

    private function publishedTemplate(): DocumentTemplate
    {
        $path = 'documents/test';
        $filename = Str::uuid().'.pdf';
        Storage::disk('local')->put("{$path}/{$filename}", $this->realPdf());

        $document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->author->id,
            'status' => Document::STATUS_READY,
            'storage_disk' => 'local',
            'storage_path' => $path,
            'stored_filename' => $filename,
            'is_encrypted' => false,
        ]);

        $versions = app(TemplateVersionService::class);
        $template = $versions->createFromDocument($document, $this->author, 'Contrato de alquiler');
        $draft = $template->versions()->first();

        DocumentTemplateField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $draft->id,
            'key' => 'arrendatario',
            'label' => 'Arrendatario',
            'type' => TemplateFieldType::TEXT,
            'y' => 40.0,
        ]);

        DocumentTemplateField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $draft->id,
            'key' => 'renta',
            'label' => 'Renta mensual',
            'type' => TemplateFieldType::NUMBER,
            'y' => 60.0,
        ]);

        DocumentTemplateSigner::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $draft->id,
            'role_key' => 'inquilino',
            'label' => 'Inquilino',
        ]);

        $versions->publish($template, $this->author);

        return $template->refresh();
    }

    /**
     * @return array<string, array{name: string, email: string}>
     */
    private function firmantes(): array
    {
        return ['inquilino' => ['name' => 'Ana Ruiz', 'email' => 'ana@ejemplo.com']];
    }

    public function test_genera_un_proceso_con_el_documento_relleno(): void
    {
        $process = $this->service->createProcess(
            $this->template,
            $this->author,
            ['arrendatario' => 'Ana Ruiz Muñoz', 'renta' => 850],
            $this->firmantes(),
        );

        $this->assertSame(SigningProcess::STATUS_DRAFT, $process->status);
        $this->assertTrue($process->cameFromTemplate());
        $this->assertSame(
            $this->template->current_version_id,
            $process->document_template_version_id
        );

        $document = $process->document;
        $contenido = Storage::disk($document->storage_disk)
            ->get($document->storage_path.'/'.$document->stored_filename);

        $texto = (new Parser)->parseContent($contenido)->getText();

        $this->assertStringContainsString('Ana Ruiz Muñoz', $texto);
        $this->assertStringContainsString('850', $texto);
        $this->assertStringContainsString('CONTRATO', $texto, 'El PDF base debe seguir ahi.');
    }

    public function test_crea_un_firmante_por_cada_rol_previsto(): void
    {
        $process = $this->service->createProcess(
            $this->template,
            $this->author,
            ['arrendatario' => 'Ana', 'renta' => 100],
            $this->firmantes(),
        );

        $signers = $process->signers()->get();

        $this->assertCount(1, $signers);
        $this->assertSame('ana@ejemplo.com', $signers->first()->email);
        $this->assertSame('Ana Ruiz', $signers->first()->name);
    }

    public function test_el_documento_generado_es_uno_normal_del_sistema(): void
    {
        // A partir de aqui firma, evidencias y verificacion no se enteran de
        // que vino de una plantilla.
        $process = $this->service->createProcess(
            $this->template,
            $this->author,
            ['arrendatario' => 'Ana', 'renta' => 100],
            $this->firmantes(),
        );

        $document = $process->document;

        $this->assertSame(Document::STATUS_READY, $document->status);
        $this->assertSame('application/pdf', $document->mime_type);
        $this->assertSame(64, strlen($document->sha256_hash));
        $this->assertSame($this->tenant->id, $document->tenant_id);
    }

    public function test_dos_envios_producen_dos_documentos_independientes(): void
    {
        $a = $this->service->createProcess($this->template, $this->author, ['arrendatario' => 'Ana', 'renta' => 100], $this->firmantes());
        $b = $this->service->createProcess($this->template, $this->author, ['arrendatario' => 'Luis', 'renta' => 200], $this->firmantes());

        $this->assertNotSame($a->document_id, $b->document_id);
        $this->assertNotSame($a->document->sha256_hash, $b->document->sha256_hash);
    }

    public function test_guarda_los_valores_usados_y_los_cifra_en_reposo(): void
    {
        $process = $this->service->createProcess(
            $this->template,
            $this->author,
            ['arrendatario' => 'Ana Ruiz', 'renta' => 850],
            $this->firmantes(),
        );

        // Leidos por el modelo: en claro.
        $this->assertSame('Ana Ruiz', $process->fresh()->template_values['arrendatario']);

        // Leidos de la base de datos: no debe aparecer el nombre.
        $crudo = DB::table('signing_processes')->where('id', $process->id)->value('template_values');

        $this->assertNotNull($crudo);
        $this->assertStringNotContainsString('Ana Ruiz', (string) $crudo, 'Los valores rellenados son datos personales.');
    }

    public function test_rechaza_un_valor_que_incumple_el_esquema(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createProcess(
            $this->template,
            $this->author,
            ['arrendatario' => 'Ana', 'renta' => 'no soy un numero'],
            $this->firmantes(),
        );
    }

    public function test_rechaza_si_falta_un_campo_obligatorio(): void
    {
        $this->expectException(ValidationException::class);

        $this->service->createProcess(
            $this->template,
            $this->author,
            ['arrendatario' => 'Ana'],
            $this->firmantes(),
        );
    }

    public function test_rechaza_si_falta_asignar_un_firmante(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('Inquilino');

        $this->service->createProcess(
            $this->template,
            $this->author,
            ['arrendatario' => 'Ana', 'renta' => 100],
            [],
        );
    }

    public function test_no_se_usa_una_plantilla_sin_habilitar(): void
    {
        $sinHabilitar = app(TemplateVersionService::class)->createFromDocument(
            Document::factory()->create([
                'tenant_id' => $this->tenant->id,
                'status' => Document::STATUS_READY,
            ]),
            $this->author,
            'Borrador',
        );

        $this->expectException(TemplateException::class);
        $this->expectExceptionMessage('no esta habilitada');

        $this->service->createProcess($sinHabilitar, $this->author, [], $this->firmantes());
    }

    public function test_nada_se_persiste_si_la_generacion_falla(): void
    {
        $antes = SigningProcess::count();

        try {
            $this->service->createProcess(
                $this->template,
                $this->author,
                ['arrendatario' => 'Ana', 'renta' => 'invalido'],
                $this->firmantes(),
            );
        } catch (ValidationException) {
            // esperado
        }

        $this->assertSame($antes, SigningProcess::count());
        $this->assertSame(0, DocumentTemplate::query()->where('id', 0)->count());
    }

    public function test_queda_registrado_en_el_audit_trail(): void
    {
        $process = $this->service->createProcess(
            $this->template,
            $this->author,
            ['arrendatario' => 'Ana', 'renta' => 100],
            $this->firmantes(),
        );

        $this->assertDatabaseHas('audit_trail_entries', [
            'auditable_type' => SigningProcess::class,
            'auditable_id' => $process->id,
            'event_type' => 'signing_process.created_from_template',
        ]);
    }
}
