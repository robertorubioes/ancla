<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RetentionPolicy;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for RetentionPolicy model.
 *
 * @extends Factory<RetentionPolicy>
 */
class RetentionPolicyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<RetentionPolicy>
     */
    protected $model = RetentionPolicy::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->words(3, true).' Policy',
            'description' => $this->faker->optional(0.7)->sentence(),
            'document_type' => $this->faker->optional(0.3)->randomElement(['contract', 'invoice', 'legal']),
            'retention_years' => $this->faker->randomElement([5, 7, 10, 15]),
            'retention_days' => 0,
            'archive_after_days' => 365,
            'deep_archive_after_days' => $this->faker->optional(0.5)->numberBetween(1825, 3650),
            'reseal_interval_days' => 365,
            'reseal_before_expiry_days' => 90,
            'auto_delete_after_expiry' => false,
            'on_expiry_action' => $this->faker->randomElement([
                RetentionPolicy::ACTION_NOTIFY,
                RetentionPolicy::ACTION_ARCHIVE,
                RetentionPolicy::ACTION_EXTEND,
            ]),
            'require_pdfa_conversion' => true,
            'target_pdfa_version' => 'PDF/A-3b',
            'is_active' => true,
            'is_default' => false,
            'priority' => $this->faker->numberBetween(1, 1000),
        ];
    }

    /**
     * Set as global policy (no tenant).
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
        ]);
    }

    /**
     * Set as default policy.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
            'priority' => 1,
        ]);
    }

    /**
     * Set as inactive policy.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set with eIDAS minimum retention (5 years).
     */
    public function eidasMinimum(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'eIDAS Minimum Retention',
            'retention_years' => 5,
            'retention_days' => 0,
            'description' => 'Minimum retention period as required by eIDAS regulation',
        ]);
    }

    /**
     * Set with long-term retention (10 years).
     */
    public function longTerm(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Long-Term Retention',
            'retention_years' => 10,
            'retention_days' => 0,
            'description' => 'Extended retention for critical documents',
        ]);
    }

    /**
     * Set with legal retention (15 years).
     */
    public function legal(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Legal Document Retention',
            'retention_years' => 15,
            'retention_days' => 0,
            'document_type' => 'legal',
            'description' => 'Extended retention for legal documents',
        ]);
    }

    /**
     * Set with auto-delete after expiry.
     */
    public function autoDelete(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_delete_after_expiry' => true,
            'on_expiry_action' => RetentionPolicy::ACTION_DELETE,
        ]);
    }

    /**
     * Set with extend action on expiry.
     */
    public function extendOnExpiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_delete_after_expiry' => false,
            'on_expiry_action' => RetentionPolicy::ACTION_EXTEND,
        ]);
    }

    /**
     * Set for a specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Set for a specific document type.
     */
    public function forDocumentType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'document_type' => $type,
        ]);
    }

    /**
     * Set without PDF/A conversion requirement.
     */
    public function noPdfaConversion(): static
    {
        return $this->state(fn (array $attributes) => [
            'require_pdfa_conversion' => false,
            'target_pdfa_version' => null,
        ]);
    }

    /**
     * Set with frequent resealing (every 6 months).
     */
    public function frequentReseal(): static
    {
        return $this->state(fn (array $attributes) => [
            'reseal_interval_days' => 180,
            'reseal_before_expiry_days' => 45,
        ]);
    }
}
