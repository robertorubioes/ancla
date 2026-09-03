<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ProcessCancelledMail;
use App\Models\Signer;
use App\Models\SigningProcess;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Job to send cancellation notification to a signer.
 */
class SendCancellationNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [60, 300, 900];

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
        Log::info('Sending cancellation notification', [
            'process_id' => $this->signingProcess->id,
            'signer_id' => $this->signer->id,
            'signer_email' => $this->signer->email,
        ]);

        Mail::to($this->signer->email)
            ->send(new ProcessCancelledMail(
                $this->signingProcess,
                $this->signer
            ));

        Log::info('Cancellation notification sent', [
            'process_id' => $this->signingProcess->id,
            'signer_id' => $this->signer->id,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to send cancellation notification', [
            'process_id' => $this->signingProcess->id,
            'signer_id' => $this->signer->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
