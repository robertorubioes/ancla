<?php

namespace App\Services\Evidence;

/**
 * Data Transfer Object for chain verification results.
 *
 * Contains the result of verifying an audit trail chain's integrity.
 */
readonly class ChainVerificationResult
{
    /**
     * Create a new ChainVerificationResult instance.
     *
     * @param  bool  $valid  Whether the chain is valid
     * @param  int  $entriesVerified  Number of entries verified
     * @param  array<string>  $errors  List of errors found
     * @param  int|null  $firstSequence  First sequence number in the chain
     * @param  int|null  $lastSequence  Last sequence number in the chain
     */
    public function __construct(
        public bool $valid,
        public int $entriesVerified,
        public array $errors = [],
        public ?int $firstSequence = null,
        public ?int $lastSequence = null
    ) {}

    /**
     * Check if the verification passed.
     *
     * @return bool True if valid
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Check if there are any errors.
     *
     * @return bool True if there are errors
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Get the number of entries in the chain.
     *
     * @return int Entry count
     */
    public function getEntryCount(): int
    {
        return $this->entriesVerified;
    }

    /**
     * Convert the result to an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'entries_verified' => $this->entriesVerified,
            'errors' => $this->errors,
            'first_sequence' => $this->firstSequence,
            'last_sequence' => $this->lastSequence,
        ];
    }
}
