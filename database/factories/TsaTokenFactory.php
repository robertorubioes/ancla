<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TsaToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TsaToken>
 */
class TsaTokenFactory extends Factory
{
    protected $model = TsaToken::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'tenant_id' => Tenant::factory(),
            'hash_algorithm' => 'SHA-256',
            'data_hash' => hash('sha256', fake()->text()),
            'token' => base64_encode(fake()->sha256()),
            'provider' => TsaToken::PROVIDER_FIRMAPROFESIONAL,
            'status' => TsaToken::STATUS_VALID,
            'issued_at' => now(),
            'verified_at' => null,
        ];
    }

    /**
     * Mark the token as verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TsaToken::STATUS_VALID,
            'verified_at' => now(),
        ]);
    }

    /**
     * Mark the token as pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TsaToken::STATUS_PENDING,
            'verified_at' => null,
        ]);
    }

    /**
     * Mark the token as invalid.
     */
    public function invalid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TsaToken::STATUS_INVALID,
            'verified_at' => now(),
        ]);
    }
}
