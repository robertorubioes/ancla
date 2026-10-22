<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RotateSuperadminPasswordTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
    }

    private function makeUser(string $email, UserRole $role): User
    {
        return User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => $email,
            'role' => $role,
            'password' => Hash::make('la-de-antes-123456'),
        ]);
    }

    public function test_aborta_si_no_hay_correo(): void
    {
        config(['superadmin.email' => null]);

        $this->artisan('superadmin:rotate-password', ['--force' => true])
            ->assertFailed();
    }

    public function test_aborta_si_el_usuario_no_existe(): void
    {
        $this->artisan('superadmin:rotate-password', [
            '--email' => 'nadie@ejemplo.test',
            '--force' => true,
        ])->assertFailed();
    }

    public function test_aborta_si_el_usuario_no_es_superadmin(): void
    {
        $user = $this->makeUser('admin@ejemplo.test', UserRole::ADMIN);

        $this->artisan('superadmin:rotate-password', [
            '--email' => $user->email,
            '--force' => true,
        ])->assertFailed();

        $this->assertTrue(
            Hash::check('la-de-antes-123456', $user->fresh()->password),
            'La contrasena no debe cambiar cuando se aborta.'
        );
    }

    public function test_rota_la_contrasena_de_un_superadmin(): void
    {
        $user = $this->makeUser('super@ejemplo.test', UserRole::SUPER_ADMIN);

        $this->artisan('superadmin:rotate-password', [
            '--email' => $user->email,
            '--force' => true,
        ])->assertSuccessful();

        $this->assertFalse(
            Hash::check('la-de-antes-123456', $user->fresh()->password),
            'La contrasena anterior debe dejar de ser valida.'
        );
    }

    public function test_la_contrasena_pedida_por_prompt_queda_activa(): void
    {
        $user = $this->makeUser('super@ejemplo.test', UserRole::SUPER_ADMIN);

        $this->artisan('superadmin:rotate-password', [
            '--email' => $user->email,
            '--ask' => true,
            '--force' => true,
        ])
            ->expectsQuestion('Nueva contrasena', 'una-contrasena-larga-y-nueva')
            ->expectsQuestion('Reptela', 'una-contrasena-larga-y-nueva')
            ->assertSuccessful();

        $this->assertTrue(
            Hash::check('una-contrasena-larga-y-nueva', $user->fresh()->password),
            'La contrasena nueva debe autenticar.'
        );
    }

    public function test_aborta_si_la_confirmacion_no_coincide(): void
    {
        $user = $this->makeUser('super@ejemplo.test', UserRole::SUPER_ADMIN);

        $this->artisan('superadmin:rotate-password', [
            '--email' => $user->email,
            '--ask' => true,
            '--force' => true,
        ])
            ->expectsQuestion('Nueva contrasena', 'una-contrasena-larga')
            ->expectsQuestion('Reptela', 'otra-distinta-larga')
            ->assertFailed();

        $this->assertTrue(
            Hash::check('la-de-antes-123456', $user->fresh()->password),
            'La contrasena no debe cambiar cuando la confirmacion falla.'
        );
    }

    public function test_rechaza_una_contrasena_demasiado_corta(): void
    {
        $user = $this->makeUser('super@ejemplo.test', UserRole::SUPER_ADMIN);

        $this->artisan('superadmin:rotate-password', [
            '--email' => $user->email,
            '--ask' => true,
            '--force' => true,
        ])
            ->expectsQuestion('Nueva contrasena', 'corta')
            ->expectsQuestion('Reptela', 'corta')
            ->assertFailed();

        $this->assertTrue(
            Hash::check('la-de-antes-123456', $user->fresh()->password),
            'La contrasena no debe cambiar cuando es demasiado corta.'
        );
    }

    public function test_no_toca_nada_si_no_se_confirma(): void
    {
        $user = $this->makeUser('super@ejemplo.test', UserRole::SUPER_ADMIN);

        $this->artisan('superadmin:rotate-password', ['--email' => $user->email])
            ->expectsConfirmation('Rotar la contrasena de esta cuenta?', 'no')
            ->assertSuccessful();

        $this->assertTrue(
            Hash::check('la-de-antes-123456', $user->fresh()->password),
            'La contrasena no debe cambiar si el operador no confirma.'
        );
    }
}
