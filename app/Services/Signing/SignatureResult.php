<?php

declare(strict_types=1);

namespace App\Services\Signing;

use App\Models\Signer;

/**
 * Result object for signature operations.
 */
readonly class SignatureResult
{
    /**
     * Create a new signature result.
     */
    public function __construct(
        public bool $success,
        public Signer $signer,
        public ?string $message = null,
    ) {}

    /**
     * Create a successful result.
     */
    public static function success(Signer $signer, ?string $message = null): self
    {
        return new self(
            success: true,
            signer: $signer,
            message: $message ?? 'Document signed successfully.',
        );
    }

    /**
     * Create a failed result.
     */
    public static function failure(Signer $signer, string $message): self
    {
        return new self(
            success: false,
            signer: $signer,
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
