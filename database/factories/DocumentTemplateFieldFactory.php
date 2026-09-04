<?php

namespace Database\Factories;

use App\Enums\TemplateFieldType;
use App\Models\DocumentTemplateField;
use App\Models\DocumentTemplateVersion;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentTemplateField>
 */
class DocumentTemplateFieldFactory extends Factory
{
    protected $model = DocumentTemplateField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'document_template_version_id' => DocumentTemplateVersion::factory(),
            'key' => 'campo_'.$this->faker->unique()->numberBetween(1, 100000),
            'label' => $this->faker->words(2, true),
            'help_text' => null,
            'type' => TemplateFieldType::TEXT,
            'required' => true,
            'default_value' => null,
            'options' => null,
            'validation' => null,
            'page' => 1,
            'x' => 20.0,
            'y' => 40.0,
            'width' => 80.0,
            'height' => 8.0,
            'font_size' => 10,
            'align' => 'left',
            'order' => 0,
        ];
    }

    public function ofType(TemplateFieldType $type): static
    {
        return $this->state(fn (array $attributes): array => ['type' => $type]);
    }

    public function optional(): static
    {
        return $this->state(fn (array $attributes): array => ['required' => false]);
    }

    /**
     * @param  array<int, array{value: string, label?: string}>  $options
     */
    public function select(array $options): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TemplateFieldType::SELECT,
            'options' => $options,
        ]);
    }

    public function at(float $x, float $y, float $width = 80.0, float $height = 8.0, int $page = 1): static
    {
        return $this->state(fn (array $attributes): array => compact('x', 'y', 'width', 'height', 'page'));
    }
}
