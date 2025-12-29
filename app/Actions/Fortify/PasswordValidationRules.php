<?php

namespace App\Actions\Fortify;

use Illuminate\Validation\Rules\Password;

/**
 * Password validation rules for ANCLA platform.
 *
 * Implements strong password requirements for security compliance.
 */
trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return [
            'required',
            'string',
            Password::min(8)
                ->mixedCase()       // Requires uppercase and lowercase
                ->numbers()         // At least one number
                ->symbols()         // At least one symbol
                ->uncompromised(),  // Not in known data breaches
            'confirmed',
        ];
    }
}
