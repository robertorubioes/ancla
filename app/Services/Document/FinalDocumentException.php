<?php

declare(strict_types=1);

namespace App\Services\Document;

use Exception;

/**
 * Exception thrown during final document generation.
 */
class FinalDocumentException extends Exception
{
    /**
     * Process is not completed.
     */
    public static function processNotCompleted(int $processId): self
    {
        return new self("Signing process {$processId} is not completed yet");
    }

    /**
     * Not all signers have signed.
     */
    public static function notAllSignersSigned(int $processId): self
    {
        return new self("Not all signers have completed signing for process {$processId}");
    }

    /**
     * No signers found for process.
     */
    public static function noSigners(int $processId): self
    {
        return new self("No signers found for process {$processId}");
    }

    /**
     * No signed documents found.
     */
    public static function noSignedDocuments(int $processId): self
    {
        return new self("No signed documents found for process {$processId}");
    }

    /**
     * Final document already generated.
     */
    public static function alreadyGenerated(int $processId): self
    {
        return new self("Final document already generated for process {$processId}");
    }

    /**
     * Signed document file not found.
     */
    public static function signedDocumentNotFound(int $documentId, string $path): self
    {
        return new self("Signed document {$documentId} not found at path: {$path}");
    }

    /**
     * Integrity check failed.
     */
    public static function integrityCheckFailed(int $documentId): self
    {
        return new self("Integrity check failed for document {$documentId}");
    }

    /**
     * PDF merge failed.
     */
    public static function mergeFailed(int $documentId, string $reason): self
    {
        return new self("Failed to merge document {$documentId}: {$reason}");
    }

    /**
     * Certification page generation failed.
     */
    public static function certificationFailed(string $reason): self
    {
        return new self("Failed to add certification page: {$reason}");
    }

    /**
     * General generation failure.
     */
    public static function generationFailed(string $reason): self
    {
        return new self("Final document generation failed: {$reason}");
    }

    /**
     * Storage operation failed.
     */
    public static function storageFailed(string $reason): self
    {
        return new self("Failed to store final document: {$reason}");
    }
}
