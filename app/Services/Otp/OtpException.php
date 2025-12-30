<?php

declare(strict_types=1);

namespace App\Services\Otp;

use Exception;

/**
 * Exception for OTP-related errors.
 */
class OtpException extends Exception
{
    /**
     * Error codes for different OTP failure scenarios.
     */
    public const CODE_RATE_LIMIT_EXCEEDED = 1001;

    public const CODE_INVALID_CODE = 1002;

    public const CODE_EXPIRED = 1003;

    public const CODE_MAX_ATTEMPTS_EXCEEDED = 1004;

    public const CODE_ALREADY_VERIFIED = 1005;

    public const CODE_NOT_FOUND = 1006;

    /**
     * Create a rate limit exceeded exception.
     */
    public static function rateLimitExceeded(): self
    {
        return new self(
            'Rate limit exceeded. Please wait before requesting another code.',
            self::CODE_RATE_LIMIT_EXCEEDED
        );
    }

    /**
     * Create an invalid code exception.
     */
    public static function invalidCode(): self
    {
        return new self(
            'Invalid verification code.',
            self::CODE_INVALID_CODE
        );
    }

    /**
     * Create an expired code exception.
     */
    public static function expired(): self
    {
        return new self(
            'Verification code has expired. Please request a new code.',
            self::CODE_EXPIRED
        );
    }

    /**
     * Create a max attempts exceeded exception.
     */
    public static function maxAttemptsExceeded(): self
    {
        return new self(
            'Maximum verification attempts exceeded. Please request a new code.',
            self::CODE_MAX_ATTEMPTS_EXCEEDED
        );
    }

    /**
     * Create an already verified exception.
     */
    public static function alreadyVerified(): self
    {
        return new self(
            'This code has already been verified.',
            self::CODE_ALREADY_VERIFIED
        );
    }

    /**
     * Create a code not found exception.
     */
    public static function notFound(): self
    {
        return new self(
            'No active verification code found.',
            self::CODE_NOT_FOUND
        );
    }
}
