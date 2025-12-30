<?php

declare(strict_types=1);

namespace App\Services\Signing;

use App\Models\Signer;
use App\Models\SigningProcess;
use App\Services\Evidence\AuditTrailService;
use Illuminate\Support\Facades\DB;

/**
 * Service for validating and managing signing access via unique links.
 *
 * This service handles the validation of signing tokens, access permissions,
 * sequential/parallel order validation, and audit trail recording.
 */
class SigningAccessService
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService
    ) {}

    /**
     * Validate access to a signing link and return detailed result.
     *
     * @param  string  $token  The unique signing token
     * @return SigningAccessResult Result containing validation status and data
     *
     * @throws SigningAccessException If access is denied
     */
    public function validateAccess(string $token): SigningAccessResult
    {
        // 1. Find signer by token
        $signer = Signer::where('token', $token)
            ->with(['signingProcess.document', 'signingProcess.createdBy', 'signingProcess.signers'])
            ->first();

        if (! $signer) {
            throw SigningAccessException::tokenNotFound($token);
        }

        $process = $signer->signingProcess;

        // 2. Validate process status (must be sent or in_progress)
        if (! in_array($process->status, [SigningProcess::STATUS_SENT, SigningProcess::STATUS_IN_PROGRESS])) {
            if ($process->status === SigningProcess::STATUS_CANCELLED) {
                throw SigningAccessException::processCancelled();
            }

            if ($process->status === SigningProcess::STATUS_COMPLETED) {
                throw SigningAccessException::processCompleted();
            }

            throw SigningAccessException::invalidStatus($process->status);
        }

        // 3. Check if process has expired
        if ($process->hasExpired()) {
            throw SigningAccessException::processExpired(
                $process->deadline_at->format('d/m/Y H:i')
            );
        }

        // 4. Check if already signed
        $alreadySigned = $signer->hasSigned();
        if ($alreadySigned) {
            // Allow viewing but mark as already signed
            return new SigningAccessResult(
                signer: $signer,
                process: $process,
                isFirstAccess: false,
                canSign: false,
                alreadySigned: true,
                errorMessage: "You have already signed this document on {$signer->signed_at->format('d/m/Y H:i')}."
            );
        }

        // 5. Check if it's the signer's turn (if sequential)
        $canSign = $this->canSign($signer);
        $waitingFor = null;

        if (! $canSign && $process->isSequential()) {
            $waitingFor = $this->getWaitingForSigner($signer);
        }

        // 6. Determine if this is first access
        $isFirstAccess = $signer->viewed_at === null;

        // 7. Register access in audit trail and update status
        if ($isFirstAccess) {
            DB::transaction(function () use ($signer, $process) {
                // Record audit trail
                $this->auditTrailService->record(
                    $process,
                    'signer.accessed',
                    [
                        'signer_id' => $signer->id,
                        'signer_email' => $signer->email,
                        'signer_name' => $signer->name,
                        'signer_order' => $signer->order,
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent(),
                        'accessed_at' => now()->toIso8601String(),
                    ]
                );

                // Update signer status to 'viewed' (first access)
                $signer->markAsViewed();

                // Update process status to in_progress if still sent
                if ($process->status === SigningProcess::STATUS_SENT) {
                    $process->markAsInProgress();
                }
            });
        }

        // 8. Return result
        return new SigningAccessResult(
            signer: $signer->fresh(),
            process: $process->fresh(),
            isFirstAccess: $isFirstAccess,
            canSign: $canSign,
            alreadySigned: false,
            errorMessage: null,
            waitingFor: $waitingFor
        );
    }

    /**
     * Check if a signer can sign now based on order and status.
     *
     * @param  Signer  $signer  The signer to check
     * @return bool True if can sign now
     */
    public function canSign(Signer $signer): bool
    {
        $process = $signer->signingProcess;

        // Must not have signed already
        if ($signer->hasSigned() || $signer->hasRejected()) {
            return false;
        }

        // Must be in valid status (sent or viewed)
        if (! in_array($signer->status, [Signer::STATUS_SENT, Signer::STATUS_VIEWED])) {
            return false;
        }

        // Process must be active
        if (! in_array($process->status, [SigningProcess::STATUS_SENT, SigningProcess::STATUS_IN_PROGRESS])) {
            return false;
        }

        // If parallel, all signers can sign simultaneously
        if ($process->isParallel()) {
            return true;
        }

        // If sequential, check if all previous signers have signed
        if ($process->isSequential()) {
            return $this->isPreviousSignersCompleted($signer);
        }

        return false;
    }

    /**
     * Check if all previous signers in sequential order have completed.
     *
     * @param  Signer  $signer  The signer to check
     * @return bool True if all previous signers have signed
     */
    private function isPreviousSignersCompleted(Signer $signer): bool
    {
        $previousSigners = $signer->signingProcess->signers()
            ->where('order', '<', $signer->order)
            ->get();

        foreach ($previousSigners as $previousSigner) {
            if (! $previousSigner->hasSigned()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the name of the signer that must sign before the current signer.
     *
     * @param  Signer  $signer  The current signer
     * @return string|null Name of the waiting signer
     */
    private function getWaitingForSigner(Signer $signer): ?string
    {
        $previousSigner = $signer->signingProcess->signers()
            ->where('order', '<', $signer->order)
            ->whereNotIn('status', [Signer::STATUS_SIGNED])
            ->orderBy('order')
            ->first();

        return $previousSigner?->name;
    }

    /**
     * Validate access and throw exception if not allowed.
     *
     * This is a convenience method that throws an exception instead of
     * returning a result with error message.
     *
     * @param  string  $token  The signing token
     * @return SigningAccessResult Validated result
     *
     * @throws SigningAccessException If access is denied
     */
    public function validateAccessOrFail(string $token): SigningAccessResult
    {
        $result = $this->validateAccess($token);

        if (! $result->isAllowed() && ! $result->hasAlreadySigned()) {
            if ($result->waitingFor) {
                throw SigningAccessException::notYourTurn($result->waitingFor);
            }

            throw new SigningAccessException($result->errorMessage ?? 'Access denied');
        }

        return $result;
    }

    /**
     * Get signing information for a token without validation.
     *
     * This is useful for displaying information about an expired or
     * completed process without throwing exceptions.
     *
     * @param  string  $token  The signing token
     * @return Signer|null The signer or null if not found
     */
    public function getSignerByToken(string $token): ?Signer
    {
        return Signer::where('token', $token)
            ->with(['signingProcess.document', 'signingProcess.createdBy'])
            ->first();
    }
}
