<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\TsaChain;
use App\Models\TsaToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for TsaChain model.
 *
 * @extends Factory<TsaChain>
 */
class TsaChainFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TsaChain>
     */
    protected $model = TsaChain::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstSealAt = $this->faker->dateTimeBetween('-2 years', '-6 months');
        $sealCount = $this->faker->numberBetween(1, 5);

        return [
            'uuid' => $this->faker->uuid(),
            'tenant_id' => Tenant::factory(),
            'document_id' => Document::factory(),
            'chain_type' => $this->faker->randomElement([
                TsaChain::TYPE_DOCUMENT,
                TsaChain::TYPE_EVIDENCE_PACKAGE,
                TsaChain::TYPE_AUDIT_TRAIL,
            ]),
            'preserved_hash' => hash('sha256', $this->faker->text()),
            'hash_algorithm' => 'SHA-256',
            'status' => TsaChain::STATUS_ACTIVE,
            'initial_tsa_token_id' => TsaToken::factory(),
            'first_seal_at' => $firstSealAt,
            'last_seal_at' => $this->faker->dateTimeBetween($firstSealAt, 'now'),
            'seal_count' => $sealCount,
            'next_seal_due_at' => $this->faker->dateTimeBetween('now', '+1 year'),
            'reseal_interval_days' => 365,
            'last_reseal_tsa_id' => $sealCount > 1 ? TsaToken::factory() : null,
            'last_verified_at' => $this->faker->optional(0.8)->dateTimeBetween('-30 days', 'now'),
            'verification_status' => $this->faker->randomElement([
                TsaChain::VERIFICATION_PENDING,
                TsaChain::VERIFICATION_VALID,
            ]),
        ];
    }

    /**
     * Set the chain as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TsaChain::STATUS_ACTIVE,
            'verification_status' => TsaChain::VERIFICATION_VALID,
        ]);
    }

    /**
     * Set the chain as broken.
     */
    public function broken(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TsaChain::STATUS_BROKEN,
            'verification_status' => TsaChain::VERIFICATION_INVALID,
        ]);
    }

    /**
     * Set the chain as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TsaChain::STATUS_EXPIRED,
            'next_seal_due_at' => $this->faker->dateTimeBetween('-1 year', '-1 month'),
        ]);
    }

    /**
     * Set the chain as needing reseal.
     */
    public function needsReseal(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TsaChain::STATUS_ACTIVE,
            'next_seal_due_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Set the chain as needing verification.
     */
    public function needsVerification(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TsaChain::STATUS_ACTIVE,
            'verification_status' => TsaChain::VERIFICATION_PENDING,
            'last_verified_at' => $this->faker->dateTimeBetween('-60 days', '-30 days'),
        ]);
    }

    /**
     * Set the chain for a specific document.
     */
    public function forDocument(Document $document): static
    {
        return $this->state(fn (array $attributes) => [
            'document_id' => $document->id,
            'tenant_id' => $document->tenant_id,
            'preserved_hash' => $document->content_hash,
        ]);
    }

    /**
     * Set the chain for a specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Set chain type to document.
     */
    public function documentType(): static
    {
        return $this->state(fn (array $attributes) => [
            'chain_type' => TsaChain::TYPE_DOCUMENT,
        ]);
    }

    /**
     * Set chain type to evidence package.
     */
    public function evidencePackageType(): static
    {
        return $this->state(fn (array $attributes) => [
            'chain_type' => TsaChain::TYPE_EVIDENCE_PACKAGE,
        ]);
    }

    /**
     * Set chain type to audit trail.
     */
    public function auditTrailType(): static
    {
        return $this->state(fn (array $attributes) => [
            'chain_type' => TsaChain::TYPE_AUDIT_TRAIL,
        ]);
    }
}
