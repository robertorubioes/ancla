<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\VerificationCode;
use App\Models\VerificationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerificationLog>
 */
class VerificationLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<VerificationLog>
     */
    protected $model = VerificationLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'verification_code_id' => VerificationCode::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'result' => 'success',
            'confidence_level' => fake()->randomElement(['high', 'medium', 'low']),
            'details' => [
                'confidence_score' => fake()->numberBetween(0, 100),
                'checks' => [
                    ['name' => 'document_hash', 'passed' => true],
                    ['name' => 'chain_hash', 'passed' => true],
                    ['name' => 'tsa_timestamp', 'passed' => fake()->boolean()],
                ],
            ],
        ];
    }

    /**
     * Indicate that the verification was successful.
     */
    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => 'success',
            'confidence_level' => 'high',
            'details' => array_merge($attributes['details'] ?? [], [
                'confidence_score' => fake()->numberBetween(90, 100),
            ]),
        ]);
    }

    /**
     * Indicate that the verification failed with invalid code.
     */
    public function invalidCode(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => 'invalid_code',
            'confidence_level' => 'low',
            'details' => [
                'error' => 'Verification code not found',
            ],
        ]);
    }

    /**
     * Indicate that the verification failed with expired code.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => 'expired',
            'confidence_level' => 'low',
            'details' => [
                'error' => 'Verification code has expired',
            ],
        ]);
    }

    /**
     * Indicate that the verification failed because document was not found.
     */
    public function documentNotFound(): static
    {
        return $this->state(fn (array $attributes) => [
            'result' => 'document_not_found',
            'confidence_level' => 'low',
            'details' => [
                'error' => 'Document not found',
            ],
        ]);
    }

    /**
     * Set a specific confidence level.
     */
    public function withConfidenceLevel(string $level): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence_level' => $level,
        ]);
    }

    /**
     * Set from a specific IP address.
     */
    public function fromIp(string $ip): static
    {
        return $this->state(fn (array $attributes) => [
            'ip_address' => $ip,
        ]);
    }
}
