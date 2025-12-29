<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Rule;
use Livewire\Component;

class TwoFactorSetup extends Component
{
    #[Rule('required|string|size:6')]
    public string $confirmationCode = '';

    public bool $showingQrCode = false;

    public bool $showingRecoveryCodes = false;

    public bool $confirming = false;

    /**
     * Enable two-factor authentication.
     */
    public function enableTwoFactor(EnableTwoFactorAuthentication $enable): void
    {
        $enable(Auth::user());

        $this->showingQrCode = true;
        $this->confirming = true;
    }

    /**
     * Confirm two-factor authentication setup.
     */
    public function confirmTwoFactor(): void
    {
        $this->validate();

        $provider = app(TwoFactorAuthenticationProvider::class);
        $user = Auth::user();

        if (! $provider->verify(decrypt($user->two_factor_secret), $this->confirmationCode)) {
            throw ValidationException::withMessages([
                'confirmationCode' => __('The provided code was invalid.'),
            ]);
        }

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->confirming = false;
        $this->showingRecoveryCodes = true;

        $this->dispatch('two-factor-enabled');
    }

    /**
     * Disable two-factor authentication.
     */
    public function disableTwoFactor(DisableTwoFactorAuthentication $disable): void
    {
        $disable(Auth::user());

        $this->reset();

        $this->dispatch('two-factor-disabled');
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate(Auth::user());

        $this->showingRecoveryCodes = true;
    }

    /**
     * Show recovery codes.
     */
    public function showRecoveryCodes(): void
    {
        $this->showingRecoveryCodes = true;
    }

    /**
     * Hide recovery codes.
     */
    public function hideRecoveryCodes(): void
    {
        $this->showingRecoveryCodes = false;
    }

    /**
     * Get the QR code SVG.
     */
    #[Computed]
    public function qrCodeSvg(): ?string
    {
        $user = Auth::user();

        if (! $user->two_factor_secret) {
            return null;
        }

        return $user->twoFactorQrCodeSvg();
    }

    /**
     * Get the setup key for manual entry.
     */
    #[Computed]
    public function setupKey(): ?string
    {
        $user = Auth::user();

        if (! $user->two_factor_secret) {
            return null;
        }

        return decrypt($user->two_factor_secret);
    }

    /**
     * Get recovery codes.
     */
    #[Computed]
    public function recoveryCodes(): array
    {
        $user = Auth::user();

        if (! $user->two_factor_recovery_codes) {
            return [];
        }

        return json_decode(decrypt($user->two_factor_recovery_codes), true);
    }

    /**
     * Check if two-factor authentication is enabled.
     */
    #[Computed]
    public function twoFactorEnabled(): bool
    {
        return Auth::user()->two_factor_confirmed_at !== null;
    }

    /**
     * Check if two-factor is pending confirmation.
     */
    #[Computed]
    public function twoFactorPendingConfirmation(): bool
    {
        $user = Auth::user();

        return $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;
    }

    public function render()
    {
        return view('livewire.auth.two-factor-setup');
    }
}
