<?php

declare(strict_types=1);

namespace App\Services\Notification;

use Exception;

/**
 * Exception thrown when completion notification fails.
 */
class CompletionNotificationException extends Exception
{
    public static function noFinalDocument(int $processId): self
    {
        return new self(
            "Final document not found for process {$processId}. Generate final document first.",
            1001
        );
    }

    public static function processNotCompleted(int $processId): self
    {
        return new self(
            "Process {$processId} is not completed. Cannot send copies.",
            1002
        );
    }

    public static function noSigners(int $processId): self
    {
        return new self(
            "No signers found for process {$processId}.",
            1003
        );
    }

    public static function invalidEmail(string $email): self
    {
        return new self(
            "Invalid email address: {$email}",
            1004
        );
    }

    public static function sendFailed(string $reason): self
    {
        return new self(
            "Failed to send completion notification: {$reason}",
            1005
        );
    }

    public static function downloadLinkExpired(): self
    {
        return new self(
            'Download link has expired.',
            1006
        );
    }

    public static function downloadLinkNotFound(string $token): self
    {
        return new self(
            "Download link not found for token: {$token}",
            1007
        );
    }
}
