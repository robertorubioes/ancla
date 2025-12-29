<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Document;
use App\Models\VerificationCode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VerificationCode>
 */
class VerificationCodeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<VerificationCode>
     */
    protected $model = VerificationCode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => fake()->uuid(),
            'document_id' => Document::factory(),
            'verification_code' => $this->generateCode(),
            'short_code' => $this->generateShortCode(),
            'qr_code_path' => null,
            'expires_at' => null,
            'access_count' => 0,
            'last_accessed_at' => null,
        ];
    }

    /**
     * Generate a verification code.
     */
    private function generateCode(): string
    {
        $charset = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < 12; $i++) {
            $code .= $charset[random_int(0, strlen($charset) - 1)];
        }

        return $code;
    }

    /**
     * Generate a short code.
     */
    private function generateShortCode(): string
    {
        $charset = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < 6; $i++) {
            $code .= $charset[random_int(0, strlen($charset) - 1)];
        }

        return $code;
    }

    /**
     * Indicate that the code is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('-1 month', '-1 day'),
        ]);
    }

    /**
     * Indicate that the code expires in the future.
     */
    public function expiresInDays(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->addDays($days),
        ]);
    }

    /**
     * Indicate that the code never expires.
     */
    public function neverExpires(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => null,
        ]);
    }

    /**
     * Indicate that the code has been accessed.
     */
    public function accessed(int $count = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'access_count' => $count,
            'last_accessed_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the code has a QR code generated.
     */
    public function withQrCode(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'qr_code_path' => sprintf(
                    'qr-codes/%s/%s.png',
                    now()->format('Y/m'),
                    $attributes['uuid']
                ),
            ];
        });
    }
}
