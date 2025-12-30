<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperadminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default tenant for superadmin (Firmalum internal)
        $superadminTenant = Tenant::firstOrCreate(
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

        // Create superadmin user
        User::firstOrCreate(
            ['email' => 'superadmin@ancla.app'],
            [
                'tenant_id' => $superadminTenant->id,
                'name' => 'Firmalum Superadmin',
                'password' => Hash::make('password'), // Change in production!
                'role' => 'super_admin',
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('✅ Superadmin user created successfully!');
        $this->command->info('   Email: superadmin@ancla.app');
        $this->command->info('   Password: password');
        $this->command->warn('⚠️  Please change the default password in production!');
    }
}
