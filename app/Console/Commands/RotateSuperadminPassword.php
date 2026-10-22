<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Rotate Superadmin Password Command
 *
 * Cambia la contrasena de una cuenta de superadministrador y la muestra una
 * unica vez. Existe para no depender de one-liners de tinker en produccion.
 *
 * La contrasena nunca se pasa por argumento: se genera aqui, de modo que no
 * queda en el historial del shell. Si hace falta una concreta, se pide por
 * prompt oculto con --ask.
 *
 * Usage:
 * - php artisan superadmin:rotate-password
 * - php artisan superadmin:rotate-password --email=otro@correo.com
 * - php artisan superadmin:rotate-password --ask
 */
class RotateSuperadminPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superadmin:rotate-password
                            {--email= : Correo de la cuenta. Por defecto, SUPERADMIN_EMAIL}
                            {--ask : Pedir la contrasena por prompt oculto en lugar de generarla}
                            {--length=24 : Longitud de la contrasena generada}
                            {--force : No pedir confirmacion}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rota la contrasena de un superadministrador y la muestra una sola vez';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email') ?: config('superadmin.email');

        if (blank($email)) {
            $this->components->error(
                'No hay correo: pasa --email o define SUPERADMIN_EMAIL en el entorno.'
            );

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error("No existe ningun usuario con el correo {$email}.");

            return self::FAILURE;
        }

        if ($user->role !== UserRole::SUPER_ADMIN) {
            $this->components->error(
                "El usuario {$email} tiene rol '{$user->role->value}', no 'super_admin'. Abortado."
            );

            return self::FAILURE;
        }

        $this->components->info('Entorno: '.app()->environment());
        $this->components->twoColumnDetail('Cuenta', $email);
        $this->components->twoColumnDetail('Nombre', (string) $user->name);

        if (! $this->option('force') && ! $this->confirm('Rotar la contrasena de esta cuenta?', false)) {
            $this->components->warn('Cancelado. No se ha cambiado nada.');

            return self::SUCCESS;
        }

        $password = $this->resolvePassword();

        if ($password === null) {
            return self::FAILURE;
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->newLine();
        $this->components->info('Contrasena rotada.');

        if (! $this->option('ask')) {
            $this->newLine();
            $this->line('  <fg=yellow;options=bold>'.$password.'</>');
            $this->newLine();
            $this->components->warn('Guardala ahora: no se vuelve a mostrar.');
        }

        $this->components->warn(
            'Las sesiones abiertas de esta cuenta siguen siendo validas hasta que caduquen.'
        );

        return self::SUCCESS;
    }

    /**
     * Obtain the new password, either generated or asked for.
     */
    private function resolvePassword(): ?string
    {
        if (! $this->option('ask')) {
            $length = max(16, (int) $this->option('length'));

            return Str::password($length);
        }

        $password = $this->secret('Nueva contrasena');
        $confirm = $this->secret('Reptela');

        if ($password !== $confirm) {
            $this->components->error('Las contrasenas no coinciden. Abortado.');

            return null;
        }

        if (mb_strlen((string) $password) < 12) {
            $this->components->error('Minimo 12 caracteres. Abortado.');

            return null;
        }

        return $password;
    }
}
