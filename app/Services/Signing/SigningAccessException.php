<?php

declare(strict_types=1);

namespace App\Services\Signing;

use Exception;

/**
 * Exception thrown when signing access validation fails.
 */
class SigningAccessException extends Exception
{
    public const CODE_TOKEN_NOT_FOUND = 1001;

    public const CODE_ALREADY_SIGNED = 1002;

    public const CODE_PROCESS_EXPIRED = 1003;

    public const CODE_PROCESS_CANCELLED = 1004;

    public const CODE_PROCESS_COMPLETED = 1005;

    public const CODE_NOT_YOUR_TURN = 1006;

    public const CODE_INVALID_STATUS = 1007;

    /**
     * Create a token not found exception.
     */
    public static function tokenNotFound(string $token): self
    {
        return new self(
            'Signing link not found or invalid.',
            self::CODE_TOKEN_NOT_FOUND
        );
    }

    /**
     * Create an already signed exception.
     */
    public static function alreadySigned(string $signedAt): self
    {
        return new self(
            "You have already signed this document on {$signedAt}.",
            self::CODE_ALREADY_SIGNED
        );
    }

    /**
     * Create a process expired exception.
     */
    public static function processExpired(string $deadline): self
    {
        return new self(
            "This signing process expired on {$deadline}.",
            self::CODE_PROCESS_EXPIRED
        );
    }

    /**
     * Create a process cancelled exception.
     */
    public static function processCancelled(): self
    {
        return new self(
            'This signing process has been cancelled.',
            self::CODE_PROCESS_CANCELLED
        );
    }

    /**
     * Create a process completed exception.
     */
    public static function processCompleted(): self
    {
        return new self(
            'This signing process has already been completed.',
            self::CODE_PROCESS_COMPLETED
        );
    }

    /**
     * Create a not your turn exception.
     */
    public static function notYourTurn(string $previousSignerName): self
    {
        return new self(
            "Please wait for {$previousSignerName} to sign first.",
            self::CODE_NOT_YOUR_TURN
        );
    }

    /**
     * Create an invalid status exception.
     */
    public static function invalidStatus(string $status): self
    {
        return new self(
            "This signing process is in an invalid state: {$status}.",
            self::CODE_INVALID_STATUS
        );
    }
}
