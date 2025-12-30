<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\SigningProcess;

/**
 * Result object for completion notification operations.
 */
readonly class CompletionNotificationResult
{
    public function __construct(
        public bool $success,
        public int $totalSigners,
        public int $notifiedCount,
        public array $errors,
        public SigningProcess $signingProcess
    ) {}

    /**
     * Check if all signers were notified.
     */
    public function allNotified(): bool
    {
        return $this->notifiedCount === $this->totalSigners;
    }

    /**
     * Check if any signers were notified.
     */
    public function anyNotified(): bool
    {
        return $this->notifiedCount > 0;
    }

    /**
     * Check if there were errors.
     */
    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    /**
     * Get error count.
     */
    public function errorCount(): int
    {
        return count($this->errors);
    }
}
