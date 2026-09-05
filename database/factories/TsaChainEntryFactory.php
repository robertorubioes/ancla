<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TsaChain;
use App\Models\TsaChainEntry;
use App\Models\TsaToken;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for TsaChainEntry model.
 *
 * @extends Factory<TsaChainEntry>
 */
class TsaChainEntryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TsaChainEntry>
     */
    protected $model = TsaChainEntry::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sealedAt = $this->faker->dateTimeBetween('-2 years', 'now');

        return [
            'uuid' => $this->faker->uuid(),
            'tsa_chain_id' => TsaChain::factory(),
            'sequence_number' => 0,
            'tsa_token_id' => TsaToken::factory(),
            'previous_entry_hash' => null,
            'cumulative_hash' => hash('sha256', $this->faker->text()),
            'sealed_hash' => hash('sha256', $this->faker->text()),
            'reseal_reason' => TsaChainEntry::REASON_INITIAL,
            'tsa_provider' => $this->faker->randomElement(['FreeTSA', 'DigiCert', 'GlobalSign']),
            'algorithm_used' => 'SHA-256',
            'timestamp_value' => $sealedAt,
            'sealed_at' => $sealedAt,
            'expires_at' => $this->faker->dateTimeBetween('+1 year', '+3 years'),
            'previous_entry_id' => null,
            'metadata' => null,
            'created_at' => $sealedAt,
        ];
    }

    /**
     * Set as initial entry (sequence 0).
     */
    public function initial(): static
    {
        return $this->state(fn (array $attributes) => [
            'sequence_number' => 0,
            'previous_entry_hash' => null,
            'previous_entry_id' => null,
            'reseal_reason' => TsaChainEntry::REASON_INITIAL,
        ]);
    }

    /**
     * Set as reseal entry.
     */
    public function reseal(int $sequenceNumber = 1, ?TsaChainEntry $previousEntry = null): static
    {
        return $this->state(fn (array $attributes) => [
            'sequence_number' => $sequenceNumber,
            'previous_entry_hash' => $previousEntry?->cumulative_hash ?? hash('sha256', $this->faker->text()),
            'previous_entry_id' => $previousEntry?->id,
            'reseal_reason' => TsaChainEntry::REASON_SCHEDULED,
        ]);
    }

    /**
     * Set with scheduled reseal reason.
     */
    public function scheduledReseal(): static
    {
        return $this->state(fn (array $attributes) => [
            'reseal_reason' => TsaChainEntry::REASON_SCHEDULED,
        ]);
    }

    /**
     * Set with algorithm upgrade reason.
     */
    public function algorithmUpgrade(): static
    {
        return $this->state(fn (array $attributes) => [
            'reseal_reason' => TsaChainEntry::REASON_ALGORITHM_UPGRADE,
        ]);
    }

    /**
     * Set with certificate expiry reason.
     */
    public function certificateExpiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'reseal_reason' => TsaChainEntry::REASON_CERTIFICATE_EXPIRY,
        ]);
    }

    /**
     * Set with manual reseal reason.
     */
    public function manualReseal(): static
    {
        return $this->state(fn (array $attributes) => [
            'reseal_reason' => TsaChainEntry::REASON_MANUAL,
        ]);
    }

    /**
     * Set as expiring soon.
     */
    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('now', '+60 days'),
        ]);
    }

    /**
     * Set as expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => $this->faker->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }

    /**
     * Set for a specific chain.
     */
    public function forChain(TsaChain $chain): static
    {
        return $this->state(fn (array $attributes) => [
            'tsa_chain_id' => $chain->id,
        ]);
    }

    /**
     * Set with a specific sequence number.
     */
    public function withSequenceNumber(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'sequence_number' => $number,
            'reseal_reason' => $number === 0 ? TsaChainEntry::REASON_INITIAL : TsaChainEntry::REASON_SCHEDULED,
        ]);
    }
}
