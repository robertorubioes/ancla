<?php

declare(strict_types=1);

namespace App\Services\Otp;

use App\Models\OtpCode;

/**
 * Result object for OTP operations.
 */
class OtpResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?OtpCode $otpCode = null,
        public readonly ?string $message = null,
        public readonly ?string $code = null,
    ) {}

    /**
     * Create a success result.
     */
    public static function success(OtpCode $otpCode, ?string $message = null, ?string $code = null): self
    {
        return new self(
            success: true,
            otpCode: $otpCode,
            message: $message,
            code: $code,
        );
    }

    /**
     * Create a failure result.
     */
    public static function failure(?string $message = null): self
    {
        return new self(
            success: false,
            message: $message,
        );
    }

    /**
     * Check if the operation was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if the operation failed.
     */
    public function isFailure(): bool
    {
        return ! $this->success;
    }
}
