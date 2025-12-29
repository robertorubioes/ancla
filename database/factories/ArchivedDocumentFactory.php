<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ArchivedDocument;
use App\Models\Document;
use App\Models\RetentionPolicy;
use App\Models\Tenant;
use App\Models\TsaChain;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for ArchivedDocument model.
 *
 * @extends Factory<ArchivedDocument>
 */
class ArchivedDocumentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ArchivedDocument>
     */
    protected $model = ArchivedDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $archivedAt = $this->faker->dateTimeBetween('-2 years', '-1 month');

        return [
            'uuid' => $this->faker->uuid(),
            'tenant_id' => Tenant::factory(),
            'document_id' => Document::factory(),
            'archive_tier' => $this->faker->randomElement([
                ArchivedDocument::TIER_HOT,
                ArchivedDocument::TIER_COLD,
                ArchivedDocument::TIER_ARCHIVE,
            ]),
            'original_storage_path' => 'documents/'.$this->faker->uuid().'.pdf',
            'archive_storage_path' => 'archive/'.$this->faker->uuid().'.pdf',
            'storage_disk' => 'local',
            'storage_bucket' => null,
            'retention_policy_id' => null,
            'content_hash' => hash('sha256', $this->faker->text()),
            'hash_algorithm' => 'SHA-256',
            'archive_hash' => hash('sha256', $this->faker->text()),
            'format_version' => '1.0',
            'current_format' => $this->faker->randomElement(['PDF', 'PDF/A']),
            'pdfa_version' => $this->faker->optional(0.3)->randomElement(['PDF/A-1b', 'PDF/A-3b']),
            'format_migrated_at' => $this->faker->optional(0.3)->dateTimeBetween('-1 year', 'now'),
            'archived_at' => $archivedAt,
            'next_reseal_at' => $this->faker->dateTimeBetween('now', '+1 year'),
            'retention_expires_at' => $this->faker->dateTimeBetween('+3 years', '+7 years'),
            'last_verified_at' => $this->faker->optional(0.8)->dateTimeBetween('-30 days', 'now'),
            'last_accessed_at' => $this->faker->optional(0.5)->dateTimeBetween('-90 days', 'now'),
            'initial_tsa_token_id' => null,
            'current_tsa_chain_id' => null,
            'reseal_count' => $this->faker->numberBetween(0, 5),
            'archive_status' => ArchivedDocument::STATUS_ACTIVE,
            'status_reason' => null,
            'metadata' => [
                'original_filename' => $this->faker->words(3, true).'.pdf',
                'original_size' => $this->faker->numberBetween(10000, 10000000),
            ],
        ];
    }

    /**
     * Set the document as active in hot tier.
     */
    public function hotTier(): static
    {
        return $this->state(fn (array $attributes) => [
            'archive_tier' => ArchivedDocument::TIER_HOT,
            'archived_at' => $this->faker->dateTimeBetween('-6 months', '-1 month'),
        ]);
    }

    /**
     * Set the document as active in cold tier.
     */
    public function coldTier(): static
    {
        return $this->state(fn (array $attributes) => [
            'archive_tier' => ArchivedDocument::TIER_COLD,
            'archived_at' => $this->faker->dateTimeBetween('-3 years', '-1 year'),
        ]);
    }

    /**
     * Set the document as active in archive tier.
     */
    public function archiveTier(): static
    {
        return $this->state(fn (array $attributes) => [
            'archive_tier' => ArchivedDocument::TIER_ARCHIVE,
            'archived_at' => $this->faker->dateTimeBetween('-5 years', '-3 years'),
        ]);
    }

    /**
     * Set the document as needing reseal.
     */
    public function needsReseal(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_reseal_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Set the document with expiring retention.
     */
    public function expiringRetention(): static
    {
        return $this->state(fn (array $attributes) => [
            'retention_expires_at' => $this->faker->dateTimeBetween('now', '+60 days'),
        ]);
    }

    /**
     * Set the document with expired retention.
     */
    public function expiredRetention(): static
    {
        return $this->state(fn (array $attributes) => [
            'retention_expires_at' => $this->faker->dateTimeBetween('-60 days', '-1 day'),
        ]);
    }

    /**
     * Set the document with a specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Set the document with a specific policy.
     */
    public function withPolicy(RetentionPolicy $policy): static
    {
        return $this->state(fn (array $attributes) => [
            'retention_policy_id' => $policy->id,
        ]);
    }

    /**
     * Set the document with a TSA chain.
     */
    public function withTsaChain(): static
    {
        return $this->afterCreating(function (ArchivedDocument $archived) {
            $chain = TsaChain::factory()->forDocument($archived->document)->create([
                'tenant_id' => $archived->tenant_id,
            ]);

            $archived->update([
                'current_tsa_chain_id' => $chain->id,
                'initial_tsa_token_id' => $chain->initial_tsa_token_id,
            ]);
        });
    }
}
