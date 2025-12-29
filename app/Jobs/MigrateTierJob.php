<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ArchivedDocument;
use App\Services\Archive\LongTermArchiveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to migrate a document between storage tiers in the background.
 *
 * This job is dispatched when a document needs to be moved
 * between hot, cold, and archive storage tiers.
 */
class MigrateTierJob implements ShouldQueue
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
    public $timeout = 300; // 5 minutes for large file transfers

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly ArchivedDocument $archivedDocument,
        public readonly string $targetTier
    ) {
        $this->onQueue(config('archive.tier_migration.queue', 'archive'));
    }

    /**
     * Execute the job.
     */
    public function handle(LongTermArchiveService $archiveService): void
    {
        $sourceTier = $this->archivedDocument->archive_tier;

        Log::info('Starting tier migration job', [
            'archived_document_id' => $this->archivedDocument->id,
            'document_id' => $this->archivedDocument->document_id,
            'source_tier' => $sourceTier,
            'target_tier' => $this->targetTier,
        ]);

        try {
            $archiveService->moveTier($this->archivedDocument, $this->targetTier);

            Log::info('Tier migration completed', [
                'archived_document_id' => $this->archivedDocument->id,
                'from_tier' => $sourceTier,
                'to_tier' => $this->targetTier,
            ]);

        } catch (\Exception $e) {
            Log::error('Tier migration failed', [
                'archived_document_id' => $this->archivedDocument->id,
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
        Log::error('Tier migration job permanently failed', [
            'archived_document_id' => $this->archivedDocument->id,
            'document_id' => $this->archivedDocument->document_id,
            'target_tier' => $this->targetTier,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Ensure document is marked as active (not migrating)
        $this->archivedDocument->update([
            'archive_status' => ArchivedDocument::STATUS_ACTIVE,
            'status_reason' => 'Migration failed: '.$exception->getMessage(),
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
            'tier-migration',
            'archived:'.$this->archivedDocument->id,
            'document:'.$this->archivedDocument->document_id,
            'tenant:'.$this->archivedDocument->tenant_id,
            'to:'.$this->targetTier,
        ];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }
}
