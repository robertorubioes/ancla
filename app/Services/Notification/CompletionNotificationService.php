<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Jobs\SendSignedDocumentCopyJob;
use App\Models\Signer;
use App\Models\SigningProcess;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending completion notifications to signers.
 *
 * Automatically sends signed document copies to all signers when process completes.
 */
class CompletionNotificationService
{
    /**
     * Send signed document copies to all signers.
     *
     * @throws CompletionNotificationException
     */
    public function sendCopies(SigningProcess $process): CompletionNotificationResult
    {
        Log::info('Sending copies to signers', [
            'process_id' => $process->id,
            'process_uuid' => $process->uuid,
        ]);

        // Validate process has final document
        if (! $process->hasFinalDocument()) {
            throw CompletionNotificationException::noFinalDocument($process->id);
        }

        // Validate process is completed
        if (! $process->isCompleted()) {
            throw CompletionNotificationException::processNotCompleted($process->id);
        }

        // Get all signers
        $signers = $process->signers()->where('status', 'signed')->get();

        if ($signers->isEmpty()) {
            throw CompletionNotificationException::noSigners($process->id);
        }

        $notifiedCount = 0;
        $errors = [];

        foreach ($signers as $signer) {
            try {
                $this->sendCopyToSigner($process, $signer);
                $notifiedCount++;

                Log::info('Copy sent to signer', [
                    'process_id' => $process->id,
                    'signer_id' => $signer->id,
                    'signer_email' => $signer->email,
                ]);
            } catch (\Exception $e) {
                $errors[] = [
                    'signer_id' => $signer->id,
                    'email' => $signer->email,
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to send copy to signer', [
                    'process_id' => $process->id,
                    'signer_id' => $signer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Create audit trail entry
        $this->logAuditTrail($process, $signers->count(), $notifiedCount);

        Log::info('Copies sending completed', [
            'process_id' => $process->id,
            'total' => $signers->count(),
            'notified' => $notifiedCount,
            'errors' => count($errors),
        ]);

        return new CompletionNotificationResult(
            success: $notifiedCount > 0,
            totalSigners: $signers->count(),
            notifiedCount: $notifiedCount,
            errors: $errors,
            signingProcess: $process
        );
    }

    /**
     * Send copy to a single signer.
     */
    public function sendCopyToSigner(SigningProcess $process, Signer $signer): void
    {
        // Validate signer email
        if (! filter_var($signer->email, FILTER_VALIDATE_EMAIL)) {
            throw CompletionNotificationException::invalidEmail($signer->email);
        }

        // Dispatch job with retry (3 attempts)
        SendSignedDocumentCopyJob::dispatch($process, $signer)
            ->onQueue('notifications')
            ->delay(now()->addSeconds(2)); // Small delay to batch emails

        // Update signer copy_sent_at timestamp
        $signer->update([
            'copy_sent_at' => now(),
        ]);

        // Create audit trail entry
        $this->logSignerNotified($signer);
    }

    /**
     * Resend copy to a specific signer.
     */
    public function resendCopy(SigningProcess $process, Signer $signer): void
    {
        Log::info('Resending copy to signer', [
            'process_id' => $process->id,
            'signer_id' => $signer->id,
        ]);

        if (! $process->hasFinalDocument()) {
            throw CompletionNotificationException::noFinalDocument($process->id);
        }

        $this->sendCopyToSigner($process, $signer);
    }

    /**
     * Log audit trail for copies sent.
     */
    private function logAuditTrail(SigningProcess $process, int $total, int $notified): void
    {
        // Use AuditTrailService if available
        if (method_exists($process, 'logAuditEvent')) {
            $process->logAuditEvent('completion.copies_sent', [
                'total_signers' => $total,
                'notified_count' => $notified,
                'final_document_path' => $process->final_document_path,
            ]);
        }
    }

    /**
     * Log signer notified event.
     */
    private function logSignerNotified(Signer $signer): void
    {
        if (method_exists($signer, 'logAuditEvent')) {
            $signer->logAuditEvent('signer.copy_sent', [
                'email' => $signer->email,
                'sent_at' => now()->toIso8601String(),
            ]);
        }
    }
}
