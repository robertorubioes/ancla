<?php

declare(strict_types=1);

namespace App\Services\Document;

use Exception;

/**
 * Exception thrown when document upload validation fails.
 */
class DocumentUploadException extends Exception
{
    /**
     * Create a new document upload exception.
     *
     * @param  string  $message  Error message
     * @param  array<string>  $errors  List of validation errors
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Get the validation errors.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors as a single string.
     */
    public function getErrorsAsString(string $separator = ', '): string
    {
        return implode($separator, $this->errors);
    }
}
