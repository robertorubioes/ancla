<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('layouts.auth')]
class TwoFactorChallenge extends Component
{
    #[Rule('nullable|string')]
    public string $code = '';

    #[Rule('nullable|string')]
    public string $recoveryCode = '';

    public bool $useRecoveryCode = false;

    /**
     * Verify the two-factor authentication code.
     */
    public function verify(): void
    {
        $this->validate();

        $user = Auth::user();

        if (! $user) {
            $this->redirect(route('login'), navigate: true);

            return;
        }

        if ($this->useRecoveryCode) {
            $this->verifyRecoveryCode($user);
        } else {
            $this->verifyTotpCode($user);
        }

        session()->regenerate();

        $this->redirectIntended(default: route('dashboard'), navigate: true);
    }

    /**
     * Verify TOTP code.
     */
    protected function verifyTotpCode($user): void
    {
        if (empty($this->code)) {
            throw ValidationException::withMessages([
                'code' => __('Please enter your authentication code.'),
            ]);
        }

        $provider = app(TwoFactorAuthenticationProvider::class);

        if (! $provider->verify(
            decrypt($user->two_factor_secret),
            $this->code
        )) {
            throw ValidationException::withMessages([
                'code' => __('The provided two factor authentication code was invalid.'),
            ]);
        }
    }

    /**
     * Verify recovery code.
     */
    protected function verifyRecoveryCode($user): void
    {
        if (empty($this->recoveryCode)) {
            throw ValidationException::withMessages([
                'recoveryCode' => __('Please enter a recovery code.'),
            ]);
        }

        $codes = json_decode(decrypt($user->two_factor_recovery_codes), true);

        if (! in_array($this->recoveryCode, $codes)) {
            throw ValidationException::withMessages([
                'recoveryCode' => __('The provided recovery code was invalid.'),
            ]);
        }

        // Invalidate used recovery code
        $user->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode(
                array_values(array_diff($codes, [$this->recoveryCode]))
            )),
        ])->save();

        event(new RecoveryCodeReplaced($user, $this->recoveryCode));
    }

    /**
     * Toggle between TOTP and recovery code input.
     */
    public function toggleRecoveryCode(): void
    {
        $this->useRecoveryCode = ! $this->useRecoveryCode;
        $this->reset(['code', 'recoveryCode']);
    }

    public function render()
    {
        return view('livewire.auth.two-factor-challenge');
    }
}
