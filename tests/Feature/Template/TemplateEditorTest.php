<?php

declare(strict_types=1);

namespace Tests\Feature\Template;

use App\Enums\TemplateFieldType;
use App\Enums\UserRole;
use App\Http\Middleware\IdentifyTenant;
use App\Livewire\Template\TemplateEditor;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateField;
use App\Models\DocumentTemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateEditorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private DocumentTemplate $template;

    private DocumentTemplateVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::ADMIN,
        ]);

        $document = Document::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->template = DocumentTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        $this->version = DocumentTemplateVersion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_id' => $this->template->id,
            'document_id' => $document->id,
            'created_by' => $this->admin->id,
            'published_at' => null,
        ]);
    }

    private function editor(): Testable
    {
        return Livewire::actingAs($this->admin)
            ->test(TemplateEditor::class, ['template' => $this->template]);
    }

    public function test_carga_los_campos_ya_guardados(): void
    {
        DocumentTemplateField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $this->version->id,
            'key' => 'nombre',
            'label' => 'Nombre completo',
        ]);

        $component = $this->editor();
        $component->assertOk();
        $component->assertSet('fields.0.key', 'nombre');
        $component->assertSet('fields.0.label', 'Nombre completo');

        $this->assertCount(1, (array) $component->get('fields'));
    }

    public function test_anade_un_campo_en_la_posicion_indicada(): void
    {
        $this->editor()
            ->call('addField', 2, 45.5, 120.25)
            ->assertSet('fields.0.page', 2)
            ->assertSet('fields.0.x', 45.5)
            ->assertSet('fields.0.y', 120.25)
            ->assertSet('selectedField', 0);
    }

    public function test_las_claves_generadas_no_se_repiten(): void
    {
        $component = $this->editor()
            ->call('addField')
            ->call('addField')
            ->call('addField');

        $keys = array_column((array) $component->get('fields'), 'key');

        $this->assertCount(3, $keys);
        $this->assertSame($keys, array_unique($keys), 'Cada campo nuevo debe traer una clave libre.');
    }

    public function test_mueve_un_campo(): void
    {
        $this->editor()
            ->call('addField')
            ->call('moveField', 0, 80.0, 200.0)
            ->assertSet('fields.0.x', 80.0)
            ->assertSet('fields.0.y', 200.0);
    }

    public function test_las_coordenadas_negativas_se_recortan_a_cero(): void
    {
        // El navegador puede mandar cualquier cosa; el servidor no se fia.
        $this->editor()
            ->call('addField')
            ->call('moveField', 0, -50.0, -10.0)
            ->assertSet('fields.0.x', 0.0)
            ->assertSet('fields.0.y', 0.0);
    }

    public function test_una_caja_no_puede_hacerse_invisible(): void
    {
        $this->editor()
            ->call('addField')
            ->call('moveField', 0, 10.0, 10.0, 0.5, 0.1)
            ->assertSet('fields.0.width', 5.0)
            ->assertSet('fields.0.height', 4.0);
    }

    public function test_mover_un_campo_inexistente_no_rompe_nada(): void
    {
        $component = $this->editor()->call('moveField', 99, 10.0, 10.0);
        $component->assertOk();

        $this->assertSame([], (array) $component->get('fields'));
    }

    public function test_elimina_un_campo_y_reindexa(): void
    {
        $component = $this->editor()
            ->call('addField')
            ->call('addField');

        $segundaClave = $component->get('fields.1.key');

        $component->call('removeField', 0)
            ->assertSet('fields.0.key', $segundaClave)
            ->assertSet('selectedField', null);

        $this->assertCount(1, (array) $component->get('fields'));
    }

    public function test_guarda_los_campos_en_la_base_de_datos(): void
    {
        $this->editor()
            ->call('addField', 1, 30.0, 60.0)
            ->set('fields.0.key', 'dni')
            ->set('fields.0.label', 'Documento de identidad')
            ->set('fields.0.type', TemplateFieldType::TEXT->value)
            ->call('addSignerRole')
            ->set('signerRoles.0.role_key', 'arrendatario')
            ->set('signerRoles.0.label', 'Arrendatario')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('success', 'Plantilla guardada.')
            ->assertDispatched('template-saved');

        $this->assertDatabaseHas('document_template_fields', [
            'document_template_version_id' => $this->version->id,
            'key' => 'dni',
            'label' => 'Documento de identidad',
            'page' => 1,
        ]);

        $this->assertDatabaseHas('document_template_signers', [
            'document_template_version_id' => $this->version->id,
            'role_key' => 'arrendatario',
        ]);
    }

    public function test_guardar_reemplaza_el_esquema_anterior(): void
    {
        DocumentTemplateField::factory()->create([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $this->version->id,
            'key' => 'viejo',
        ]);

        $this->editor()
            ->call('removeField', 0)
            ->call('addField')
            ->set('fields.0.key', 'nuevo')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('document_template_fields', ['key' => 'viejo']);
        $this->assertDatabaseHas('document_template_fields', ['key' => 'nuevo']);
    }

    public function test_rechaza_dos_campos_con_la_misma_clave(): void
    {
        $this->editor()
            ->call('addField')
            ->call('addField')
            ->set('fields.0.key', 'repetida')
            ->set('fields.1.key', 'repetida')
            ->call('save')
            ->assertHasErrors('fields');

        $this->assertDatabaseCount('document_template_fields', 0);
    }

    public function test_rechaza_una_clave_con_formato_invalido(): void
    {
        $this->editor()
            ->call('addField')
            ->set('fields.0.key', 'Con Espacios Y Mayusculas')
            ->call('save')
            ->assertHasErrors('fields.0.key');

        $this->assertDatabaseCount('document_template_fields', 0);
    }

    public function test_un_desplegable_sin_opciones_no_se_guarda(): void
    {
        $this->editor()
            ->call('addField')
            ->set('fields.0.key', 'plan')
            ->set('fields.0.type', TemplateFieldType::SELECT->value)
            ->set('fields.0.options', [])
            ->call('save')
            ->assertHasErrors('fields.0.options');

        $this->assertDatabaseCount('document_template_fields', 0);
    }

    public function test_rechaza_dos_roles_de_firmante_con_la_misma_clave(): void
    {
        $this->editor()
            ->call('addSignerRole')
            ->call('addSignerRole')
            ->set('signerRoles.0.role_key', 'parte')
            ->set('signerRoles.1.role_key', 'parte')
            ->call('save')
            ->assertHasErrors('signerRoles');
    }

    public function test_no_se_edita_una_version_ya_publicada(): void
    {
        // Publicada = inmutable: hay procesos de firma que apuntan a ella.
        $this->version->update(['published_at' => now()]);

        Livewire::actingAs($this->admin)
            ->test(TemplateEditor::class, ['template' => $this->template])
            ->assertStatus(409);
    }

    public function test_el_editor_exige_ser_administrador_del_tenant(): void
    {
        $operador = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => UserRole::OPERATOR,
        ]);

        $this->actingAs($operador)
            ->withoutMiddleware(IdentifyTenant::class)
            ->get(route('templates.editor', ['template' => $this->template->uuid]))
            ->assertForbidden();
    }
}
