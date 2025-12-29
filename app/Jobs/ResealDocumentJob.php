<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TsaChain;
use App\Models\TsaChainEntry;
use App\Services\Archive\TsaResealService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to re-seal a TSA chain in the background.
 *
 * This job is dispatched when a document needs its TSA chain
 * re-sealed to maintain long-term timestamp validity.
 */
class ResealDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public $backoff = [60, 300, 900]; // 1min, 5min, 15min

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly TsaChain $chain,
        public readonly string $reason = TsaChainEntry::REASON_SCHEDULED
    ) {
        $this->onQueue(config('archive.reseal.queue', 'archive'));
    }

    /**
     * Execute the job.
     */
    public function handle(TsaResealService $resealService): void
    {
        Log::info('Starting document reseal job', [
            'chain_id' => $this->chain->id,
            'document_id' => $this->chain->document_id,
            'reason' => $this->reason,
        ]);

        try {
            $newEntry = $resealService->reseal($this->chain, $this->reason);

            Log::info('Document reseal completed', [
                'chain_id' => $this->chain->id,
                'new_sequence' => $newEntry->sequence_number,
                'next_seal_due' => $this->chain->fresh()->next_seal_due_at?->toDateString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Document reseal failed', [
                'chain_id' => $this->chain->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Document reseal job permanently failed', [
            'chain_id' => $this->chain->id,
            'document_id' => $this->chain->document_id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Mark chain as having issues
        $this->chain->update([
            'verification_status' => TsaChain::VERIFICATION_INVALID,
            'status' => TsaChain::STATUS_ACTIVE, // Keep active but mark verification issue
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'reseal',
            'chain:'.$this->chain->id,
            'document:'.$this->chain->document_id,
            'tenant:'.$this->chain->tenant_id,
        ];
    }
}
