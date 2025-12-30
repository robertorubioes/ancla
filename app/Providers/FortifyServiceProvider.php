<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Fortify actions
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Custom views
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn ($request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        // Custom authentication with tenant context
        Fortify::authenticateUsing(function (Request $request) {
            $tenant = app()->bound('tenant') ? app('tenant') : null;

            // Find user by email within tenant context
            $query = User::withoutGlobalScopes()
                ->where('email', $request->email);

            // Only filter by tenant if one is bound
            if ($tenant) {
                $query->where('tenant_id', $tenant->id);
            }

            $user = $query->first();

            // Verify password
            if ($user && Hash::check($request->password, $user->password)) {
                // Store tenant_id in session for validation
                session(['tenant_id' => $user->tenant_id]);

                return $user;
            }

            return null;
        });

        // Rate limiting for login - includes tenant context
        RateLimiter::for('login', function (Request $request) {
            $tenant = app()->bound('tenant') ? app('tenant') : null;
            $throttleKey = Str::transliterate(
                Str::lower($request->input(Fortify::username())).
                '|'.$request->ip().
                '|'.($tenant?->id ?? 'global')
            );

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Rate limiting for 2FA
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
