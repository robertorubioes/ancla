<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Enums\TemplateFieldType;
use App\Enums\UserRole;
use App\Http\Middleware\IdentifyTenant;
use App\Livewire\Template\TemplateFill;
use App\Livewire\Template\TemplateIndex;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateField;
use App\Models\DocumentTemplateSigner;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Template\TemplateVersionService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateUiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

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
    }

    private function readyDocument(string $filename = 'contrato.pdf'): Document
    {
        $pdf = new \FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'BASE', 0, 1);

        $path = 'documents/test';
        $stored = Str::uuid().'.pdf';
        Storage::disk('local')->put("{$path}/{$stored}", $pdf->Output('S'));

        return Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'status' => Document::STATUS_READY,
            'original_filename' => $filename,
            'storage_disk' => 'local',
            'storage_path' => "{$path}/{$stored}",
            'stored_filename' => $stored,
            'is_encrypted' => false,
        ]);
    }

    private function usableTemplate(): DocumentTemplate
    {
        $versions = app(TemplateVersionService::class);
        $template = $versions->createFromDocument($this->readyDocument(), $this->admin, 'Contrato');
        $draft = $template->versions()->first();

        DocumentTemplateField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $draft->id,
            'key' => 'arrendatario',
            'label' => 'Arrendatario',
            'type' => TemplateFieldType::TEXT,
        ]);

        DocumentTemplateSigner::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $draft->id,
            'role_key' => 'inquilino',
            'label' => 'Inquilino',
        ]);

        $versions->publish($template, $this->admin);

        return $template->refresh();
    }

    // --------------------------------------------------------------------
    // Listado y ciclo de vida
    // --------------------------------------------------------------------

    public function test_convertir_un_documento_lleva_al_editor(): void
    {
        $document = $this->readyDocument('alquiler.pdf');

        Livewire::actingAs($this->admin)
            ->test(TemplateIndex::class)
            ->call('openCreate')
            ->set('sourceDocumentId', $document->id)
            ->set('newName', 'Contrato de alquiler')
            ->call('createFromDocument')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('document_templates', [
            'name' => 'Contrato de alquiler',
            'status' => DocumentTemplate::STATUS_DRAFT,
        ]);
    }

    public function test_elegir_documento_propone_su_nombre(): void
    {
        $document = $this->readyDocument('nomina-mensual.pdf');

        Livewire::actingAs($this->admin)
            ->test(TemplateIndex::class)
            ->call('openCreate')
            ->set('sourceDocumentId', $document->id)
            ->assertSet('newName', 'nomina-mensual');
    }

    public function test_un_documento_ya_convertido_no_vuelve_a_ofrecerse(): void
    {
        $usado = $this->readyDocument('usado.pdf');
        $libre = $this->readyDocument('libre.pdf');

        app(TemplateVersionService::class)->createFromDocument($usado, $this->admin, 'Ya es plantilla');

        $component = Livewire::actingAs($this->admin)->test(TemplateIndex::class);
        $ids = $component->instance()->availableDocuments()->pluck('id')->all();

        $this->assertContains($libre->id, $ids);
        $this->assertNotContains($usado->id, $ids);
    }

    public function test_habilitar_activa_la_plantilla(): void
    {
        $versions = app(TemplateVersionService::class);
        $template = $versions->createFromDocument($this->readyDocument(), $this->admin, 'Contrato');
        $draft = $template->versions()->first();

        DocumentTemplateField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $draft->id,
        ]);
        DocumentTemplateSigner::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $draft->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(TemplateIndex::class)
            ->call('enable', $template->uuid)
            ->assertSet('error', '');

        $this->assertSame(DocumentTemplate::STATUS_ACTIVE, $template->refresh()->status);
    }

    public function test_habilitar_sin_campos_muestra_el_motivo(): void
    {
        $versions = app(TemplateVersionService::class);
        $template = $versions->createFromDocument($this->readyDocument(), $this->admin, 'Contrato');
        DocumentTemplateSigner::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $template->versions()->first()->id,
        ]);

        $component = Livewire::actingAs($this->admin)
            ->test(TemplateIndex::class)
            ->call('enable', $template->uuid);

        $this->assertStringContainsString('ningun campo', (string) $component->get('error'));
        $this->assertSame(DocumentTemplate::STATUS_DRAFT, $template->refresh()->status);
    }

    public function test_retirar_no_borra_la_plantilla(): void
    {
        $template = $this->usableTemplate();

        Livewire::actingAs($this->admin)
            ->test(TemplateIndex::class)
            ->call('archive', $template->uuid);

        $this->assertSame(DocumentTemplate::STATUS_ARCHIVED, $template->refresh()->status);
        $this->assertDatabaseHas('document_templates', ['id' => $template->id]);
    }

    public function test_el_listado_no_muestra_plantillas_de_otro_tenant(): void
    {
        $otro = Tenant::factory()->create();
        DocumentTemplate::factory()->create(['tenant_id' => $otro->id, 'name' => 'Ajena']);
        $propia = $this->usableTemplate();

        $component = Livewire::actingAs($this->admin)->test(TemplateIndex::class);
        $nombres = $component->instance()->templates()->pluck('name')->all();

        $this->assertContains($propia->name, $nombres);
        $this->assertNotContains('Ajena', $nombres);
    }

    // --------------------------------------------------------------------
    // Formulario dinamico
    // --------------------------------------------------------------------

    public function test_el_formulario_sale_del_esquema_de_la_plantilla(): void
    {
        $template = $this->usableTemplate();

        Livewire::actingAs($this->admin)
            ->test(TemplateFill::class, ['template' => $template])
            ->assertOk()
            ->assertSee('Arrendatario')
            ->assertSee('Inquilino');
    }

    public function test_no_se_rellena_una_plantilla_sin_habilitar(): void
    {
        $template = app(TemplateVersionService::class)
            ->createFromDocument($this->readyDocument(), $this->admin, 'Borrador');

        Livewire::actingAs($this->admin)
            ->test(TemplateFill::class, ['template' => $template])
            ->assertStatus(409);
    }

    public function test_genera_el_proceso_con_los_valores_rellenados(): void
    {
        $template = $this->usableTemplate();

        Livewire::actingAs($this->admin)
            ->test(TemplateFill::class, ['template' => $template])
            ->set('values.arrendatario', 'Ana Ruiz')
            ->set('signers.0.name', 'Ana Ruiz')
            ->set('signers.0.email', 'ana@ejemplo.com')
            ->set('sendNow', false)
            ->call('generate')
            ->assertHasNoErrors()
            ->assertRedirect(route('signing-processes.index'));

        $process = SigningProcess::first();

        $this->assertNotNull($process);
        $this->assertTrue($process->cameFromTemplate());
        $this->assertSame('Ana Ruiz', $process->template_values['arrendatario']);
        $this->assertSame('ana@ejemplo.com', $process->signers()->first()->email);
    }

    public function test_exige_los_campos_obligatorios_del_esquema(): void
    {
        $template = $this->usableTemplate();

        Livewire::actingAs($this->admin)
            ->test(TemplateFill::class, ['template' => $template])
            ->set('values.arrendatario', '')
            ->set('signers.0.name', 'Ana')
            ->set('signers.0.email', 'ana@ejemplo.com')
            ->call('generate')
            ->assertHasErrors('values.arrendatario');

        $this->assertSame(0, SigningProcess::count());
    }

    public function test_exige_asignar_cada_firmante_previsto(): void
    {
        $template = $this->usableTemplate();

        Livewire::actingAs($this->admin)
            ->test(TemplateFill::class, ['template' => $template])
            ->set('values.arrendatario', 'Ana')
            ->set('signers.0.name', '')
            ->set('signers.0.email', '')
            ->call('generate')
            ->assertHasErrors(['signers.0.name', 'signers.0.email']);

        $this->assertSame(0, SigningProcess::count());
    }

    public function test_las_paginas_se_renderizan_completas(): void
    {
        // Las pruebas de componente no pasan por la ruta ni por el layout.
        $template = $this->usableTemplate();

        $this->actingAs($this->admin)
            ->withoutMiddleware(IdentifyTenant::class)
            ->get(route('templates.index'))
            ->assertOk()
            ->assertSee('Plantillas');

        $this->actingAs($this->admin)
            ->withoutMiddleware(IdentifyTenant::class)
            ->get(route('templates.fill', ['template' => $template->uuid]))
            ->assertOk()
            ->assertSee('Arrendatario');

        $this->actingAs($this->admin)
            ->withoutMiddleware(IdentifyTenant::class)
            // La version publicada no se edita sin abrir antes un borrador.
            ->get(route('templates.editor', ['template' => $template->uuid]))
            ->assertStatus(409);
    }

    public function test_un_operador_no_entra_a_las_plantillas(): void
    {
        $operador = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::OPERATOR,
        ]);

        $this->actingAs($operador)
            ->withoutMiddleware(IdentifyTenant::class)
            ->get(route('templates.index'))
            ->assertForbidden();
    }
}
