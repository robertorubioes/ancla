<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\DocumentTemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentTemplateVersion>
 */
class DocumentTemplateVersionFactory extends Factory
{
    protected $model = DocumentTemplateVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'document_template_id' => DocumentTemplate::factory(),
            'version' => 1,
            'document_id' => Document::factory(),
            'created_by' => User::factory(),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'published_at' => now(),
        ]);
    }
}
