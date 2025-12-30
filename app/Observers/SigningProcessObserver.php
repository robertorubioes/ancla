<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\SigningProcess;
use App\Services\Document\FinalDocumentException;
use App\Services\Notification\CompletionNotificationException;
use Illuminate\Support\Facades\Log;

/**
 * Observer for SigningProcess model events.
 *
 * Handles automatic final document generation and notification sending when process is completed.
 */
class SigningProcessObserver
{
    /**
     * Handle the SigningProcess "updated" event.
     *
     * Automatically generate final document and send copies when process status changes to completed.
     */
    public function updated(SigningProcess $signingProcess): void
    {
        // Check if status was changed to completed
        if (! $signingProcess->wasChanged('status')) {
            return;
        }

        if ($signingProcess->status !== SigningProcess::STATUS_COMPLETED) {
            return;
        }

        // Check if final document already exists
        if ($signingProcess->hasFinalDocument()) {
            Log::info('Final document already exists, skipping generation', [
                'process_id' => $signingProcess->id,
            ]);

            // Try to send copies if not already sent
            $this->trySendCopies($signingProcess);

            return;
        }

        // Generate final document automatically
        try {
            Log::info('Auto-generating final document after process completion', [
                'process_id' => $signingProcess->id,
                'process_uuid' => $signingProcess->uuid,
            ]);

            $result = $signingProcess->generateFinalDocument();

            Log::info('Final document auto-generated successfully', [
                'process_id' => $signingProcess->id,
                'document_path' => $result->storagePath,
                'document_hash' => $result->contentHash,
                'page_count' => $result->pageCount,
            ]);

            // After successful generation, send copies to signers
            $this->trySendCopies($signingProcess->fresh());
        } catch (FinalDocumentException $e) {
            // Log error but don't fail the completion
            // Final document can be generated manually later
            Log::error('Failed to auto-generate final document', [
                'process_id' => $signingProcess->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        } catch (\Exception $e) {
            Log::error('Unexpected error during final document auto-generation', [
                'process_id' => $signingProcess->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Try to send copies to signers.
     */
    private function trySendCopies(SigningProcess $signingProcess): void
    {
        try {
            Log::info('Auto-sending copies to signers', [
                'process_id' => $signingProcess->id,
            ]);

            $result = $signingProcess->sendCopies();

            Log::info('Copies sent successfully', [
                'process_id' => $signingProcess->id,
                'total_signers' => $result->totalSigners,
                'notified_count' => $result->notifiedCount,
            ]);
        } catch (CompletionNotificationException $e) {
            // Log error but don't fail
            // Copies can be sent manually later
            Log::error('Failed to auto-send copies', [
                'process_id' => $signingProcess->id,
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            Log::error('Unexpected error during copy sending', [
                'process_id' => $signingProcess->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
