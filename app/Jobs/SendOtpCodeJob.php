<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\OtpCodeMail;
use App\Models\OtpCode;
use App\Models\Signer;
use App\Services\Evidence\AuditTrailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Job to send OTP verification code via email.
 */
class SendOtpCodeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly OtpCode $otpCode,
        public readonly Signer $signer,
        public readonly string $code,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AuditTrailService $auditTrailService): void
    {
        try {
            // Send email with OTP code
            Mail::to($this->signer->email)
                ->send(new OtpCodeMail(
                    signer: $this->signer,
                    code: $this->code,
                    expiresMinutes: config('otp.expires_minutes', 10),
                ));

            // Mark as sent
            $this->otpCode->markAsSent();

            // Log successful send
            $auditTrailService->log(
                action: 'otp.sent',
                entityType: 'signer',
                entityId: $this->signer->id,
                metadata: [
                    'otp_code_id' => $this->otpCode->id,
                    'signer_email' => $this->signer->email,
                    'signing_process_id' => $this->signer->signing_process_id,
                    'sent_at' => now()->toIso8601String(),
                ]
            );
        } catch (Throwable $e) {
            // Log failure
            $auditTrailService->log(
                action: 'otp.send_failed',
                entityType: 'signer',
                entityId: $this->signer->id,
                metadata: [
                    'otp_code_id' => $this->otpCode->id,
                    'signer_email' => $this->signer->email,
                    'error' => $e->getMessage(),
                    'attempt' => $this->attempts(),
                ]
            );

            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        $auditTrailService = app(AuditTrailService::class);

        $auditTrailService->log(
            action: 'otp.send_permanently_failed',
            entityType: 'signer',
            entityId: $this->signer->id,
            metadata: [
                'otp_code_id' => $this->otpCode->id,
                'signer_email' => $this->signer->email,
                'error' => $exception?->getMessage(),
                'attempts' => $this->tries,
            ]
        );
    }
}
