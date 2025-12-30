<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('layouts.auth')]
class LoginForm extends Component
{
    #[Rule('required|email')]
    public string $email = '';

    #[Rule('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * Attempt to authenticate the user.
     */
    public function login(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        $tenant = app()->bound('tenant') ? app('tenant') : null;

        // Build credentials array - only include tenant_id if tenant is bound
        $credentials = [
            'email' => $this->email,
            'password' => $this->password,
        ];

        if ($tenant) {
            $credentials['tenant_id'] = $tenant->id;
        }

        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        session()->regenerate();

        // Store tenant_id in session for validation (use authenticated user's tenant)
        $user = Auth::user();
        session(['tenant_id' => $user->tenant_id]);

        $this->redirectIntended(default: route('dashboard'), navigate: true);
    }

    /**
     * Ensure the login request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    protected function throttleKey(): string
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;

        return strtolower($this->email).'|'.request()->ip().'|'.($tenant?->id ?? 'global');
    }

    public function render()
    {
        return view('livewire.auth.login-form');
    }
}
