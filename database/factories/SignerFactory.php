<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Signer;
use App\Models\SigningProcess;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Signer>
 */
class SignerFactory extends Factory
{
    protected $model = Signer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'signing_process_id' => SigningProcess::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'order' => 0,
            'status' => Signer::STATUS_PENDING,
            'token' => Str::random(32),
            'sent_at' => null,
            'viewed_at' => null,
            'signed_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the signer is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Signer::STATUS_PENDING,
            'sent_at' => null,
        ]);
    }

    /**
     * Indicate that the signer has been sent the request.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Signer::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Indicate that the signer has viewed the document.
     */
    public function viewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Signer::STATUS_VIEWED,
            'sent_at' => now()->subHours(2),
            'viewed_at' => now(),
        ]);
    }

    /**
     * Indicate that the signer has signed the document.
     */
    public function signed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Signer::STATUS_SIGNED,
            'sent_at' => now()->subHours(3),
            'viewed_at' => now()->subHours(2),
            'signed_at' => now(),
        ]);
    }

    /**
     * Indicate that the signer has rejected the document.
     */
    public function rejected(?string $reason = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Signer::STATUS_REJECTED,
            'sent_at' => now()->subHours(2),
            'viewed_at' => now()->subHour(),
            'rejected_at' => now(),
            'rejection_reason' => $reason ?? fake()->sentence(),
        ]);
    }

    /**
     * Set the signing order.
     */
    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order' => $order,
        ]);
    }

    /**
     * Set a specific email.
     */
    public function withEmail(string $email): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $email,
        ]);
    }

    /**
     * Set a specific name.
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }

    /**
     * Set a specific token.
     */
    public function withToken(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'token' => $token,
        ]);
    }
}
