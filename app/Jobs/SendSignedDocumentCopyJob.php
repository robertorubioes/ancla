<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\SignedDocumentCopyMail;
use App\Models\Signer;
use App\Models\SigningProcess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Job to send signed document copy to a signer.
 *
 * Includes retry logic with exponential backoff.
 */
class SendSignedDocumentCopyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [60, 300, 900]; // 1min, 5min, 15min

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public SigningProcess $signingProcess,
        public Signer $signer
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Sending signed document copy', [
            'process_id' => $this->signingProcess->id,
            'signer_id' => $this->signer->id,
            'signer_email' => $this->signer->email,
            'attempt' => $this->attempts(),
        ]);

        // Generate download token (valid for 30 days)
        $downloadToken = Str::random(64);
        $expiresAt = now()->addDays(30);

        // Update signer with download token
        $this->signer->update([
            'download_token' => $downloadToken,
            'download_expires_at' => $expiresAt,
        ]);

        // Send email with download link
        Mail::to($this->signer->email)
            ->send(new SignedDocumentCopyMail(
                $this->signingProcess,
                $this->signer,
                $downloadToken
            ));

        Log::info('Signed document copy email sent', [
            'process_id' => $this->signingProcess->id,
            'signer_id' => $this->signer->id,
            'token_expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send signed document copy', [
            'process_id' => $this->signingProcess->id,
            'signer_id' => $this->signer->id,
            'signer_email' => $this->signer->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Could send notification to admin here
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'completion-notification',
            'process:'.$this->signingProcess->id,
            'signer:'.$this->signer->id,
        ];
    }
}
