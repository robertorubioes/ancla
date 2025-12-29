<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

#[Layout('layouts.auth')]
class ForgotPassword extends Component
{
    #[Rule('required|email')]
    public string $email = '';

    public bool $emailSent = false;

    /**
     * Send the password reset link.
     */
    public function sendResetLink(): void
    {
        $this->validate();

        // Note: We don't reveal whether the email exists for security
        $status = Password::sendResetLink(
            ['email' => $this->email]
        );

        if ($status === Password::RESET_LINK_SENT) {
            $this->emailSent = true;
            session()->flash('status', __($status));
        } else {
            // For security, always show success message
            // This prevents email enumeration attacks
            $this->emailSent = true;
            session()->flash('status', __('If an account with that email exists, we have sent a password reset link.'));
        }
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
