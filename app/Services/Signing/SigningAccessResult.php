<?php

declare(strict_types=1);

namespace App\Services\Signing;

use App\Models\Signer;
use App\Models\SigningProcess;

/**
 * Result object for signing access validation.
 */
class SigningAccessResult
{
    public function __construct(
        public readonly Signer $signer,
        public readonly SigningProcess $process,
        public readonly bool $isFirstAccess,
        public readonly bool $canSign,
        public readonly bool $alreadySigned,
        public readonly ?string $errorMessage = null,
        public readonly ?string $waitingFor = null
    ) {}

    /**
     * Check if access is allowed.
     */
    public function isAllowed(): bool
    {
        return $this->errorMessage === null;
    }

    /**
     * Check if the signer has already signed.
     */
    public function hasAlreadySigned(): bool
    {
        return $this->alreadySigned;
    }

    /**
     * Check if this is the first time the signer accessed the link.
     */
    public function isFirstVisit(): bool
    {
        return $this->isFirstAccess;
    }

    /**
     * Check if the signer can proceed to sign.
     */
    public function canProceedToSign(): bool
    {
        return $this->canSign && ! $this->alreadySigned;
    }

    /**
     * Get the document information.
     */
    public function getDocument(): mixed
    {
        return $this->process->document;
    }

    /**
     * Get the promoter (creator) of the signing process.
     */
    public function getPromoter(): mixed
    {
        return $this->process->createdBy;
    }

    /**
     * Get the custom message for the signer.
     */
    public function getCustomMessage(): ?string
    {
        return $this->process->custom_message;
    }

    /**
     * Get the deadline for signing.
     */
    public function getDeadline(): mixed
    {
        return $this->process->deadline_at;
    }

    /**
     * Get total signers count.
     */
    public function getTotalSigners(): int
    {
        return $this->process->getTotalSignersCount();
    }

    /**
     * Get completed signers count.
     */
    public function getCompletedSigners(): int
    {
        return $this->process->getCompletedSignersCount();
    }

    /**
     * Get the signer's order in the signing sequence.
     */
    public function getSignerOrder(): int
    {
        return $this->signer->order;
    }

    /**
     * Check if the signing is sequential or parallel.
     */
    public function isSequential(): bool
    {
        return $this->process->isSequential();
    }

    /**
     * Get the name of the signer currently waiting for (sequential only).
     */
    public function getWaitingForName(): ?string
    {
        return $this->waitingFor;
    }
}
