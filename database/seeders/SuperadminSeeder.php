<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SuperadminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Las credenciales NO se cablean: se leen de SUPERADMIN_EMAIL y
     * SUPERADMIN_PASSWORD. Fuera de local/testing la contrasena es
     * obligatoria; si falta, el seeder aborta en lugar de crear una cuenta
     * con una clave conocida.
     */
    public function run(): void
    {
        $email = config('superadmin.email');

        if (blank($email)) {
            $this->command->error('SUPERADMIN_EMAIL no esta definido. Seeder abortado.');

            return;
        }

        $password = config('superadmin.password');
        $generated = false;

        if (blank($password)) {
            if (app()->environment(['production', 'staging'])) {
                $this->command->error(
                    'SUPERADMIN_PASSWORD es obligatorio en '.app()->environment().'. Seeder abortado.'
                );

                return;
            }

            $password = Str::password(20);
            $generated = true;
        }

        $superadminTenant = Tenant::firstOrCreate(
            // Slug historico: renombrarlo crearia un tenant duplicado en produccion.
            ['slug' => 'ancla-admin'],
            [
                'name' => 'Firmalum Admin',
                'subdomain' => 'admin',
                'plan' => 'enterprise',
                'status' => 'active',
                'max_users' => null,
                'max_documents_per_month' => null,
                'settings' => [
                    'branding' => [
                        'logo' => null,
                        'primary_color' => '#3B82F6',
                        'secondary_color' => '#1E40AF',
                    ],
                    'timezone' => 'Europe/Madrid',
                    'locale' => 'en',
                ],
            ]
        );

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'tenant_id' => $superadminTenant->id,
                'name' => config('superadmin.name'),
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ]
        );

        if (! $user->wasRecentlyCreated) {
            $this->command->info("El superadmin {$email} ya existia; no se toca su contrasena.");

            return;
        }

        $this->command->info('Superadmin creado.');
        $this->command->info("   Email: {$email}");

        if ($generated) {
            $this->command->warn("   Contrasena generada: {$password}");
            $this->command->warn('   Guardala ahora: no se vuelve a mostrar.');
        }
    }
}
