<?php

namespace Database\Factories;

use App\Models\DocumentTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentTemplate>
 */
class DocumentTemplateFactory extends Factory
{
    protected $model = DocumentTemplate::class;

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
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'status' => DocumentTemplate::STATUS_DRAFT,
            'created_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentTemplate::STATUS_ACTIVE,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DocumentTemplate::STATUS_ARCHIVED,
        ]);
    }
}
