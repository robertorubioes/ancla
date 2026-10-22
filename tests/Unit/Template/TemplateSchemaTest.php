<?php

declare(strict_types=1);

namespace Tests\Unit\Template;

use App\Enums\TemplateFieldType;
use App\Models\DocumentTemplateField;
use App\Models\DocumentTemplateVersion;
use App\Models\Tenant;
use App\Services\Template\TemplateSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TemplateSchemaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private DocumentTemplateVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->version = DocumentTemplateVersion::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function field(array $attributes = []): DocumentTemplateField
    {
        return DocumentTemplateField::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'document_template_version_id' => $this->version->id,
        ], $attributes));
    }

    public function test_los_campos_calculados_quedan_fuera_del_formulario(): void
    {
        $this->field(['key' => 'nombre', 'type' => TemplateFieldType::TEXT]);
        $this->field(['key' => 'firmante', 'type' => TemplateFieldType::SIGNER_NAME]);
        $this->field(['key' => 'fecha', 'type' => TemplateFieldType::TODAY]);

        $schema = TemplateSchema::for($this->version);

        $this->assertSame(['nombre'], $schema->inputFields()->pluck('key')->all());
        $this->assertEqualsCanonicalizing(
            ['firmante', 'fecha'],
            $schema->computedFields()->pluck('key')->all()
        );
    }

    public function test_genera_reglas_segun_el_tipo(): void
    {
        $this->field(['key' => 'nombre', 'type' => TemplateFieldType::TEXT]);
        $this->field(['key' => 'importe', 'type' => TemplateFieldType::NUMBER]);
        $this->field(['key' => 'alta', 'type' => TemplateFieldType::DATE, 'required' => false]);

        $rules = TemplateSchema::for($this->version)->rules();

        $this->assertSame(['required', 'string', 'max:255'], $rules['nombre']);
        $this->assertSame(['required', 'numeric'], $rules['importe']);
        $this->assertSame(['nullable', 'date'], $rules['alta']);
    }

    public function test_un_desplegable_solo_acepta_sus_opciones(): void
    {
        $this->field([
            'key' => 'plan',
            'type' => TemplateFieldType::SELECT,
            'options' => [
                ['value' => 'mensual', 'label' => 'Mensual'],
                ['value' => 'anual', 'label' => 'Anual'],
            ],
        ]);

        $schema = TemplateSchema::for($this->version);

        $this->assertContains('in:mensual,anual', $schema->rules()['plan']);

        $this->assertSame(['plan' => 'anual'], $schema->validate(['plan' => 'anual']));

        $this->expectException(ValidationException::class);
        $schema->validate(['plan' => 'trimestral']);
    }

    public function test_el_prefijo_permite_encajar_con_el_formulario_web(): void
    {
        $this->field(['key' => 'dni', 'type' => TemplateFieldType::TEXT]);

        $rules = TemplateSchema::for($this->version)->rules('values');

        $this->assertArrayHasKey('values.dni', $rules);
        $this->assertArrayNotHasKey('dni', $rules);
    }

    public function test_los_mensajes_usan_la_etiqueta_del_campo(): void
    {
        $this->field(['key' => 'dni', 'label' => 'Documento de identidad']);

        $attributes = TemplateSchema::for($this->version)->attributes();

        $this->assertSame('Documento de identidad', $attributes['dni']);
    }

    public function test_traduce_las_reglas_extra_declaradas(): void
    {
        $this->field([
            'key' => 'importe',
            'type' => TemplateFieldType::NUMBER,
            'validation' => ['min' => 100, 'max' => 5000],
        ]);

        $rules = TemplateSchema::for($this->version)->rules();

        $this->assertContains('min:100', $rules['importe']);
        $this->assertContains('max:5000', $rules['importe']);
    }

    public function test_ignora_reglas_extra_que_no_esten_en_la_lista_blanca(): void
    {
        // `validation` lo edita quien crea la plantilla: no debe poder
        // inyectar reglas arbitrarias de Laravel.
        $this->field([
            'key' => 'nombre',
            'validation' => ['exists' => 'users,email', 'unique' => 'users'],
        ]);

        $rules = TemplateSchema::for($this->version)->rules();

        $this->assertSame(['required', 'string', 'max:255'], $rules['nombre']);
    }

    public function test_validate_descarta_los_valores_desconocidos(): void
    {
        $this->field(['key' => 'nombre']);

        $limpio = TemplateSchema::for($this->version)->validate([
            'nombre' => 'Maria',
            'colado' => 'no deberia pasar',
        ]);

        $this->assertSame(['nombre' => 'Maria'], $limpio);
    }

    public function test_un_campo_obligatorio_que_falta_se_rechaza(): void
    {
        $this->field(['key' => 'nombre', 'required' => true]);

        $this->expectException(ValidationException::class);

        TemplateSchema::for($this->version)->validate([]);
    }

    public function test_expone_los_valores_por_defecto(): void
    {
        $this->field(['key' => 'ciudad', 'default_value' => 'Madrid']);
        $this->field(['key' => 'acepta', 'type' => TemplateFieldType::CHECKBOX, 'default_value' => '1']);

        $defaults = TemplateSchema::for($this->version)->defaults();

        $this->assertSame('Madrid', $defaults['ciudad']);
        $this->assertTrue($defaults['acepta']);
    }

    public function test_se_describe_para_la_documentacion_de_la_api(): void
    {
        $this->field([
            'key' => 'nombre',
            'label' => 'Nombre completo',
            'type' => TemplateFieldType::TEXT,
            'required' => true,
        ]);
        $this->field(['key' => 'hoy', 'type' => TemplateFieldType::TODAY]);

        $described = TemplateSchema::for($this->version)->toArray();

        $this->assertCount(1, $described, 'Los campos calculados no se documentan como entrada.');
        $this->assertSame('nombre', $described[0]['key']);
        $this->assertSame('Nombre completo', $described[0]['label']);
        $this->assertSame('text', $described[0]['type']);
        $this->assertTrue($described[0]['required']);
    }
}
