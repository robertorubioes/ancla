<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'uuid' => Str::uuid()->toString(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::random(4),
            'domain' => null,
            'logo_path' => null,
            'primary_color' => '#3B82F6',
            'secondary_color' => '#1E40AF',
            'status' => 'active',
            'plan' => 'starter',
            'trial_ends_at' => null,
            'settings' => [],
        ];
    }

    /**
     * Indicate that the tenant is on trial.
     */
    public function onTrial(int $days = 14): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays($days),
        ]);
    }

    /**
     * Indicate that the tenant trial has expired.
     */
    public function expiredTrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trial',
            'trial_ends_at' => now()->subDays(1),
        ]);
    }

    /**
     * Indicate that the tenant is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }

    /**
     * Indicate that the tenant is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Set a specific plan for the tenant.
     */
    public function withPlan(string $plan): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => $plan,
        ]);
    }

    /**
     * Set a custom domain for the tenant.
     */
    public function withDomain(string $domain): static
    {
        return $this->state(fn (array $attributes) => [
            'domain' => $domain,
        ]);
    }

    /**
     * Set custom branding for the tenant.
     */
    public function withBranding(string $primaryColor, string $secondaryColor, ?string $logoPath = null): static
    {
        return $this->state(fn (array $attributes) => [
            'primary_color' => $primaryColor,
            'secondary_color' => $secondaryColor,
            'logo_path' => $logoPath,
        ]);
    }

    /**
     * Add specific settings to the tenant.
     */
    public function withSettings(array $settings): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], $settings),
        ]);
    }
}
