<?php

declare(strict_types=1);

namespace App\Services\Signing;

use Exception;

class PdfSignatureException extends Exception
{
    public static function certificateLoadFailed(string $reason): self
    {
        return new self("Failed to load certificate: {$reason}");
    }

    public static function privateKeyLoadFailed(string $reason): self
    {
        return new self("Failed to load private key: {$reason}");
    }

    public static function pkcs7CreationFailed(string $reason): self
    {
        return new self("Failed to create PKCS#7 signature: {$reason}");
    }

    public static function tsaRequestFailed(string $reason): self
    {
        return new self("TSA timestamp request failed: {$reason}");
    }

    public static function pdfEmbeddingFailed(string $reason): self
    {
        return new self("Failed to embed signature in PDF: {$reason}");
    }

    public static function pdfReadFailed(string $path): self
    {
        return new self("Failed to read PDF file: {$path}");
    }

    public static function pdfWriteFailed(string $path, string $reason): self
    {
        return new self("Failed to write signed PDF to {$path}: {$reason}");
    }

    public static function invalidPadesLevel(string $level): self
    {
        return new self("Invalid PAdES level: {$level}. Supported: B-B, B-LT, B-LTA");
    }

    public static function signatureTooLarge(int $size, int $maxSize): self
    {
        return new self("PKCS#7 signature too large ({$size} bytes). Maximum: {$maxSize} bytes");
    }

    public static function signerNotReady(string $reason): self
    {
        return new self("Signer not ready for PDF signature: {$reason}");
    }

    public static function validationFailed(string $reason): self
    {
        return new self("Signature validation failed: {$reason}");
    }
}
