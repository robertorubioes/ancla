<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::VIEWER->value,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Set user as super admin (no tenant).
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
            'role' => UserRole::SUPER_ADMIN->value,
        ]);
    }

    /**
     * Set user as admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::ADMIN->value,
        ]);
    }

    /**
     * Set user as operator.
     */
    public function operator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::OPERATOR->value,
        ]);
    }

    /**
     * Set user as viewer.
     */
    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::VIEWER->value,
        ]);
    }

    /**
     * Assign user to a specific tenant.
     */
    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * User with 2FA enabled.
     */
    public function withTwoFactorEnabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
