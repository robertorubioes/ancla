<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Jobs\SendSigningRequestJob;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Services\Evidence\AuditTrailService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing signing request notifications.
 *
 * Handles sending email notifications to signers with proper
 * sequential/parallel logic and audit trail logging.
 */
class SigningNotificationService
{
    /**
     * Create a new SigningNotificationService instance.
     */
    public function __construct(
        private readonly AuditTrailService $auditTrailService
    ) {}

    /**
     * Send signing request notifications for a signing process.
     *
     * @param  SigningProcess  $signingProcess  The signing process
     * @return SigningNotificationResult Result of the notification operation
     *
     * @throws SigningNotificationException If the process is not in draft state
     */
    public function sendNotifications(SigningProcess $signingProcess): SigningNotificationResult
    {
        // Validate state
        if (! $signingProcess->isDraft()) {
            throw new SigningNotificationException(
                "Cannot send notifications for signing process in '{$signingProcess->status}' state. Must be 'draft'."
            );
        }

        return DB::transaction(function () use ($signingProcess) {
            // Load signers
            $signers = $signingProcess->signers()->inOrder()->get();

            if ($signers->isEmpty()) {
                throw new SigningNotificationException(
                    'Cannot send notifications. No signers found for signing process.'
                );
            }

            // Determine which signers to notify based on signing order
            $signersToNotify = $this->getSignersToNotify($signingProcess, $signers);

            // Queue notification jobs for each signer
            $queuedCount = 0;
            foreach ($signersToNotify as $signer) {
                SendSigningRequestJob::dispatch($signingProcess->id, $signer->id);
                $queuedCount++;
            }

            // Update signing process status to 'sent'
            $signingProcess->markAsSent();

            // Log in audit trail
            $this->auditTrailService->record(
                auditable: $signingProcess,
                event: 'signing_process.sent',
                payload: [
                    'total_signers' => $signers->count(),
                    'notified_signers' => $queuedCount,
                    'signature_order' => $signingProcess->signature_order,
                    'deadline_at' => $signingProcess->deadline_at?->toIso8601String(),
                ]
            );

            Log::info('Signing request notifications queued', [
                'signing_process_id' => $signingProcess->id,
                'total_signers' => $signers->count(),
                'notified_count' => $queuedCount,
                'order' => $signingProcess->signature_order,
            ]);

            return new SigningNotificationResult(
                success: true,
                totalSigners: $signers->count(),
                notifiedCount: $queuedCount,
                signingProcess: $signingProcess->fresh()
            );
        });
    }

    /**
     * Get the signers that should be notified based on signing order.
     *
     * @param  SigningProcess  $signingProcess  The signing process
     * @param  Collection<int, Signer>  $signers  All signers
     * @return Collection<int, Signer> Signers to notify
     */
    private function getSignersToNotify(SigningProcess $signingProcess, Collection $signers): Collection
    {
        // If parallel signing, notify all signers
        if ($signingProcess->isParallel()) {
            return $signers;
        }

        // If sequential signing, notify only the first signer (lowest order)
        if ($signingProcess->isSequential()) {
            $firstSigner = $signers->sortBy('order')->first();

            return $firstSigner ? collect([$firstSigner]) : collect();
        }

        // Default to parallel
        return $signers;
    }

    /**
     * Resend notification to a specific signer.
     *
     * @param  Signer  $signer  The signer to resend to
     * @return bool True if notification was queued successfully
     *
     * @throws SigningNotificationException If the signer cannot be notified
     */
    public function resendNotification(Signer $signer): bool
    {
        $signingProcess = $signer->signingProcess;

        // Validate process is not completed/cancelled
        if (in_array($signingProcess->status, [
            SigningProcess::STATUS_COMPLETED,
            SigningProcess::STATUS_CANCELLED,
            SigningProcess::STATUS_EXPIRED,
        ])) {
            throw new SigningNotificationException(
                "Cannot resend notification. Signing process is {$signingProcess->status}."
            );
        }

        // Validate signer has not already signed
        if ($signer->hasSigned()) {
            throw new SigningNotificationException(
                'Cannot resend notification. Signer has already signed.'
            );
        }

        // Queue the notification job
        SendSigningRequestJob::dispatch($signingProcess->id, $signer->id);

        // Log in audit trail
        $this->auditTrailService->record(
            auditable: $signingProcess,
            event: 'signer.notification_resent',
            payload: [
                'signer_id' => $signer->id,
                'signer_email' => $signer->email,
                'signer_name' => $signer->name,
            ]
        );

        Log::info('Signing request notification resent', [
            'signing_process_id' => $signingProcess->id,
            'signer_id' => $signer->id,
            'email' => $signer->email,
        ]);

        return true;
    }

    /**
     * Notify the next signer in a sequential signing process.
     *
     * Called when a signer completes their signature in a sequential process.
     *
     * @param  SigningProcess  $signingProcess  The signing process
     * @return bool True if next signer was notified, false if no more signers
     */
    public function notifyNextSigner(SigningProcess $signingProcess): bool
    {
        // Only applicable for sequential signing
        if (! $signingProcess->isSequential()) {
            return false;
        }

        // Get next pending signer
        $nextSigner = $signingProcess->getNextSigner();

        if (! $nextSigner) {
            return false;
        }

        // Queue notification
        SendSigningRequestJob::dispatch($signingProcess->id, $nextSigner->id);

        Log::info('Next signer notified in sequential process', [
            'signing_process_id' => $signingProcess->id,
            'signer_id' => $nextSigner->id,
            'email' => $nextSigner->email,
            'order' => $nextSigner->order,
        ]);

        return true;
    }
}
