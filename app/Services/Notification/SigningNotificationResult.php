<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\SigningProcess;

/**
 * Result object for signing notification operations.
 */
readonly class SigningNotificationResult
{
    /**
     * Create a new SigningNotificationResult instance.
     *
     * @param  bool  $success  Whether the operation was successful
     * @param  int  $totalSigners  Total number of signers in the process
     * @param  int  $notifiedCount  Number of signers notified
     * @param  SigningProcess  $signingProcess  The updated signing process
     */
    public function __construct(
        public bool $success,
        public int $totalSigners,
        public int $notifiedCount,
        public SigningProcess $signingProcess
    ) {}

    /**
     * Check if all signers were notified.
     */
    public function allNotified(): bool
    {
        return $this->totalSigners === $this->notifiedCount;
    }

    /**
     * Get the number of signers not notified.
     */
    public function pendingCount(): int
    {
        return max(0, $this->totalSigners - $this->notifiedCount);
    }
}
