<?php

namespace Database\Factories;

use App\Models\DocumentTemplateSigner;
use App\Models\DocumentTemplateVersion;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentTemplateSigner>
 */
class DocumentTemplateSignerFactory extends Factory
{
    protected $model = DocumentTemplateSigner::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => fn (): mixed => app()->bound('tenant') && app('tenant')
                ? app('tenant')->id
                : Tenant::factory(),
            'document_template_version_id' => DocumentTemplateVersion::factory(),
            'role_key' => 'rol_'.$this->faker->unique()->numberBetween(1, 100000),
            'label' => $this->faker->words(2, true),
            'order' => 0,
            'signature_page' => null,
            'signature_x' => null,
            'signature_y' => null,
        ];
    }
}
