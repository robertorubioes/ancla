<?php

declare(strict_types=1);

namespace App\Services\Signing;

use DateTimeInterface;

class SignatureValidationResult
{
    public function __construct(
        public readonly bool $isValid,
        public readonly bool $hashValid,
        public readonly bool $pkcs7Valid,
        public readonly bool $tsaValid,
        public readonly bool $certificateValid,
        public readonly DateTimeInterface $validatedAt,
        public readonly ?string $errorMessage = null,
        public readonly array $warnings = []
    ) {}

    /**
     * Check if all validations passed.
     */
    public function isFullyValid(): bool
    {
        return $this->isValid
            && $this->hashValid
            && $this->pkcs7Valid
            && $this->tsaValid
            && $this->certificateValid;
    }

    /**
     * Get validation summary.
     */
    public function getSummary(): array
    {
        return [
            'valid' => $this->isValid,
            'checks' => [
                'hash' => $this->hashValid,
                'pkcs7' => $this->pkcs7Valid,
                'timestamp' => $this->tsaValid,
                'certificate' => $this->certificateValid,
            ],
            'validated_at' => $this->validatedAt->format('Y-m-d H:i:s'),
            'error' => $this->errorMessage,
            'warnings' => $this->warnings,
        ];
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'hash_valid' => $this->hashValid,
            'pkcs7_valid' => $this->pkcs7Valid,
            'tsa_valid' => $this->tsaValid,
            'certificate_valid' => $this->certificateValid,
            'validated_at' => $this->validatedAt->format('c'),
            'error_message' => $this->errorMessage,
            'warnings' => $this->warnings,
        ];
    }
}
