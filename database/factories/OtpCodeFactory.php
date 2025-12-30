<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OtpCode;
use App\Models\Signer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OtpCode>
 */
class OtpCodeFactory extends Factory
{
    protected $model = OtpCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'signer_id' => Signer::factory(),
            'code_hash' => Hash::make('123456'), // Default test code
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'verified' => false,
            'verified_at' => null,
            'sent_at' => now(),
        ];
    }

    /**
     * Indicate that the OTP code is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinutes(1),
        ]);
    }

    /**
     * Indicate that the OTP code is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified' => true,
            'verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the OTP code has exceeded max attempts.
     */
    public function maxAttemptsExceeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'attempts' => config('otp.max_attempts', 5),
        ]);
    }

    /**
     * Indicate that the OTP code has not been sent yet.
     */
    public function notSent(): static
    {
        return $this->state(fn (array $attributes) => [
            'sent_at' => null,
        ]);
    }

    /**
     * Set a custom code for testing.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code_hash' => Hash::make($code),
        ]);
    }

    /**
     * Set a custom expiration time.
     */
    public function expiresIn(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addMinutes($minutes),
        ]);
    }

    /**
     * Set a custom attempts count.
     */
    public function withAttempts(int $attempts): static
    {
        return $this->state(fn (array $attributes) => [
            'attempts' => $attempts,
        ]);
    }
}
