<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use App\Models\SigningProcess;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SigningProcess>
 */
class SigningProcessFactory extends Factory
{
    protected $model = SigningProcess::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => Str::uuid()->toString(),
            'tenant_id' => Tenant::factory(),
            'document_id' => Document::factory(),
            'created_by' => User::factory(),
            'status' => SigningProcess::STATUS_DRAFT,
            'signature_order' => SigningProcess::ORDER_PARALLEL,
            'custom_message' => fake()->optional()->sentence(),
            'deadline_at' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'completed_at' => null,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the signing process is in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SigningProcess::STATUS_DRAFT,
        ]);
    }

    /**
     * Indicate that the signing process has been sent.
     */
    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SigningProcess::STATUS_SENT,
        ]);
    }

    /**
     * Indicate that the signing process is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SigningProcess::STATUS_IN_PROGRESS,
        ]);
    }

    /**
     * Indicate that the signing process is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SigningProcess::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the signing process is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SigningProcess::STATUS_EXPIRED,
            'deadline_at' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the signing process is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SigningProcess::STATUS_CANCELLED,
        ]);
    }

    /**
     * Indicate that the signing order is sequential.
     */
    public function sequential(): static
    {
        return $this->state(fn (array $attributes) => [
            'signature_order' => SigningProcess::ORDER_SEQUENTIAL,
        ]);
    }

    /**
     * Indicate that the signing order is parallel.
     */
    public function parallel(): static
    {
        return $this->state(fn (array $attributes) => [
            'signature_order' => SigningProcess::ORDER_PARALLEL,
        ]);
    }

    /**
     * Indicate that the signing process has a deadline.
     */
    public function withDeadline(?int $days = 7): static
    {
        return $this->state(fn (array $attributes) => [
            'deadline_at' => now()->addDays($days),
        ]);
    }

    /**
     * Indicate that the signing process has a custom message.
     */
    public function withMessage(string $message): static
    {
        return $this->state(fn (array $attributes) => [
            'custom_message' => $message,
        ]);
    }
}
