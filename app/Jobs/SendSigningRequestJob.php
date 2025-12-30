<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\SigningRequestMail;
use App\Models\Signer;
use App\Models\SigningProcess;
use App\Services\Evidence\AuditTrailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Job to send signing request email to a signer.
 *
 * Uses queues with automatic retry logic for failed sends.
 */
class SendSigningRequestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * Number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $signingProcessId,
        public readonly int $signerId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AuditTrailService $auditTrailService): void
    {
        try {
            // Load models fresh from database
            $signingProcess = SigningProcess::with(['document', 'createdBy'])->findOrFail($this->signingProcessId);
            $signer = Signer::findOrFail($this->signerId);

            // Validate email address
            if (! filter_var($signer->email, FILTER_VALIDATE_EMAIL)) {
                Log::error('Invalid email address for signer', [
                    'signer_id' => $signer->id,
                    'email' => $signer->email,
                ]);

                $this->recordFailure($signingProcess, $signer, $auditTrailService, 'Invalid email address');

                return;
            }

            // Send the email
            Mail::to($signer->email)
                ->send(new SigningRequestMail($signingProcess, $signer));

            // Mark signer as sent
            $signer->markAsSent();

            // Log successful send in audit trail
            $auditTrailService->record(
                auditable: $signingProcess,
                event: 'signer.notified',
                payload: [
                    'signer_id' => $signer->id,
                    'signer_email' => $signer->email,
                    'signer_name' => $signer->name,
                    'notification_method' => 'email',
                    'attempt' => $this->attempts(),
                ]
            );

            Log::info('Signing request email sent successfully', [
                'signing_process_id' => $signingProcess->id,
                'signer_id' => $signer->id,
                'email' => $signer->email,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to send signing request email', [
                'signing_process_id' => $this->signingProcessId,
                'signer_id' => $this->signerId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // If this was the last attempt, record failure
            if ($this->attempts() >= $this->tries) {
                try {
                    $signingProcess = SigningProcess::find($this->signingProcessId);
                    $signer = Signer::find($this->signerId);

                    if ($signingProcess && $signer) {
                        $this->recordFailure($signingProcess, $signer, $auditTrailService, $e->getMessage());
                    }
                } catch (Throwable $recordError) {
                    Log::error('Failed to record email failure', [
                        'error' => $recordError->getMessage(),
                    ]);
                }
            }

            throw $e;
        }
    }

    /**
     * Record email send failure.
     */
    private function recordFailure(
        SigningProcess $signingProcess,
        Signer $signer,
        AuditTrailService $auditTrailService,
        string $reason
    ): void {
        // Update signer metadata to track failure
        $metadata = $signer->metadata ?? [];
        $metadata['email_send_failed'] = true;
        $metadata['email_send_failure_reason'] = $reason;
        $metadata['email_send_failure_at'] = now()->toIso8601String();

        $signer->update(['metadata' => $metadata]);

        // Log failure in audit trail
        $auditTrailService->record(
            auditable: $signingProcess,
            event: 'signer.notification_failed',
            payload: [
                'signer_id' => $signer->id,
                'signer_email' => $signer->email,
                'signer_name' => $signer->name,
                'notification_method' => 'email',
                'failure_reason' => $reason,
                'attempts' => $this->attempts(),
            ]
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('SendSigningRequestJob failed permanently', [
            'signing_process_id' => $this->signingProcessId,
            'signer_id' => $this->signerId,
            'attempts' => $this->tries,
            'error' => $exception?->getMessage(),
        ]);
    }
}
