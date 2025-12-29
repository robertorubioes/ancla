<?php

declare(strict_types=1);

namespace App\Services\Document;

use Exception;

/**
 * Exception thrown when a duplicate document is detected.
 */
class DuplicateDocumentException extends Exception
{
    /**
     * Create a new duplicate document exception.
     *
     * @param  string  $message  Error message
     * @param  string  $existingUuid  UUID of the existing document
     */
    public function __construct(
        string $message,
        public readonly string $existingUuid,
    ) {
        parent::__construct($message);
    }

    /**
     * Get the UUID of the existing document.
     */
    public function getExistingUuid(): string
    {
        return $this->existingUuid;
    }
}
