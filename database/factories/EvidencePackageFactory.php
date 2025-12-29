<?php

namespace Database\Factories;

use App\Models\EvidencePackage;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EvidencePackage>
 */
class EvidencePackageFactory extends Factory
{
    protected $model = EvidencePackage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'tenant_id' => Tenant::factory(),
            'packagable_type' => 'App\\Models\\Document',
            'packagable_id' => fake()->randomNumber(5),
            'document_hash' => hash('sha256', Str::random(100)),
            'audit_trail_hash' => hash('sha256', Str::random(100)),
            'tsa_token_id' => null,
            'status' => EvidencePackage::STATUS_PENDING,
            'generated_at' => null,
        ];
    }

    /**
     * Configure the package as ready.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EvidencePackage::STATUS_READY,
            'generated_at' => now(),
        ]);
    }

    /**
     * Configure the package as generating.
     */
    public function generating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EvidencePackage::STATUS_GENERATING,
        ]);
    }

    /**
     * Configure the package as failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EvidencePackage::STATUS_FAILED,
        ]);
    }
}
