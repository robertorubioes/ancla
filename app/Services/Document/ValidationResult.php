<?php

declare(strict_types=1);

namespace App\Services\Document;

/**
 * Data Transfer Object for PDF validation results.
 */
readonly class ValidationResult
{
    /**
     * Create a new validation result.
     *
     * @param  bool  $valid  Whether the validation passed
     * @param  array<string>  $errors  List of validation errors
     * @param  array<string>  $warnings  List of validation warnings
     * @param  array<string, mixed>  $metadata  Extracted PDF metadata
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
        public array $warnings = [],
        public array $metadata = [],
    ) {}

    /**
     * Check if the validation passed.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Check if the validation failed.
     */
    public function isFailed(): bool
    {
        return ! $this->valid;
    }

    /**
     * Check if there are warnings.
     */
    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    /**
     * Get the first error message.
     */
    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * Get all errors as a single string.
     */
    public function getErrorsAsString(string $separator = ', '): string
    {
        return implode($separator, $this->errors);
    }

    /**
     * Get a specific metadata value.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get page count from metadata.
     */
    public function getPageCount(): int
    {
        return $this->metadata['page_count'] ?? 0;
    }

    /**
     * Check if PDF has JavaScript.
     */
    public function hasJavaScript(): bool
    {
        return $this->metadata['has_javascript'] ?? false;
    }

    /**
     * Check if PDF is encrypted.
     */
    public function hasEncryption(): bool
    {
        return $this->metadata['has_encryption'] ?? false;
    }

    /**
     * Check if PDF has existing signatures.
     */
    public function hasSignatures(): bool
    {
        return $this->metadata['has_signatures'] ?? false;
    }

    /**
     * Check if PDF is PDF/A compliant.
     */
    public function isPdfA(): bool
    {
        return $this->metadata['is_pdf_a'] ?? false;
    }

    /**
     * Get PDF version.
     */
    public function getPdfVersion(): ?string
    {
        return $this->metadata['pdf_version'] ?? null;
    }

    /**
     * Convert to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'metadata' => $this->metadata,
        ];
    }
}
