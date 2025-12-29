<?php

declare(strict_types=1);

namespace App\Services\Archive;

use App\Models\ArchivedDocument;
use App\Models\Document;
use App\Models\RetentionPolicy;
use App\Services\Evidence\HashingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Service for managing long-term document archiving.
 *
 * Implements document archiving, tier management, and restoration
 * for eIDAS-compliant 5+ year retention.
 */
class LongTermArchiveService
{
    public function __construct(
        private readonly HashingService $hashingService,
        private readonly TsaResealService $resealService,
        private readonly RetentionPolicyService $policyService
    ) {}

    /**
     * Archive a document for long-term preservation.
     */
    public function archive(Document $document, ?RetentionPolicy $policy = null): ArchivedDocument
    {
        return DB::transaction(function () use ($document, $policy) {
            // Get applicable retention policy
            $policy = $policy ?? $this->policyService->getPolicyForDocument($document);
            $policySettings = $this->policyService->applyPolicy($document, $policy);

            // Calculate archive hash
            $archiveHash = $this->calculateArchiveHash($document);

            // Determine storage path
            $archivePath = $this->generateArchivePath($document);

            // Copy to archive storage
            $this->copyToArchiveStorage($document, $archivePath);

            // Create archived document record
            $archived = ArchivedDocument::create([
                'uuid' => Str::uuid()->toString(),
                'tenant_id' => $document->tenant_id,
                'document_id' => $document->id,
                'archive_tier' => ArchivedDocument::TIER_HOT,
                'original_storage_path' => $document->stored_path,
                'archive_storage_path' => $archivePath,
                'storage_disk' => config('archive.tiers.hot.storage_disk', 'local'),
                'storage_bucket' => config('archive.tiers.hot.storage_bucket'),
                'retention_policy_id' => $policy->id ?? null,
                'content_hash' => $document->content_hash,
                'hash_algorithm' => 'SHA-256',
                'archive_hash' => $archiveHash,
                'format_version' => '1.0',
                'current_format' => $document->is_pdf_a ? 'PDF/A' : 'PDF',
                'pdfa_version' => $document->is_pdf_a ? 'PDF/A-3b' : null,
                'archived_at' => now(),
                'next_reseal_at' => $policySettings['next_reseal_at'],
                'retention_expires_at' => $policySettings['retention_expires_at'],
                'archive_status' => ArchivedDocument::STATUS_ACTIVE,
                'metadata' => [
                    'original_filename' => $document->original_name,
                    'original_size' => $document->file_size,
                    'page_count' => $document->page_count,
                    'archived_by' => auth()->id(),
                    'policy_applied' => $policy->name ?? 'default',
                ],
            ]);

            // Initialize TSA chain
            $chain = $this->resealService->initializeChain($document);

            // Link chain to archived document
            $archived->update([
                'initial_tsa_token_id' => $chain->initial_tsa_token_id,
                'current_tsa_chain_id' => $chain->id,
            ]);

            Log::info('Document archived for long-term retention', [
                'archived_document_id' => $archived->id,
                'document_id' => $document->id,
                'retention_expires_at' => $archived->retention_expires_at->toDateString(),
            ]);

            return $archived->fresh(['tsaChain', 'retentionPolicy']);
        });
    }

    /**
     * Move a document to a different storage tier.
     */
    public function moveTier(ArchivedDocument $archived, string $newTier): ArchivedDocument
    {
        $validTiers = [
            ArchivedDocument::TIER_HOT,
            ArchivedDocument::TIER_COLD,
            ArchivedDocument::TIER_ARCHIVE,
        ];

        if (! in_array($newTier, $validTiers)) {
            throw new \InvalidArgumentException("Invalid tier: {$newTier}");
        }

        if ($archived->archive_tier === $newTier) {
            return $archived;
        }

        return DB::transaction(function () use ($archived, $newTier) {
            $oldTier = $archived->archive_tier;

            // Mark as migrating
            $archived->update(['archive_status' => ArchivedDocument::STATUS_MIGRATING]);

            try {
                // Get tier configuration
                $tierConfig = config("archive.tiers.{$newTier}");
                $newDisk = $tierConfig['storage_disk'];
                $newPath = $this->generateTierPath($archived, $newTier);

                // Copy file to new tier storage
                $this->copyBetweenTiers($archived, $newDisk, $newPath);

                // Update record
                $archived->update([
                    'archive_tier' => $newTier,
                    'archive_storage_path' => $newPath,
                    'storage_disk' => $newDisk,
                    'storage_bucket' => $tierConfig['storage_bucket'] ?? null,
                    'archive_status' => ArchivedDocument::STATUS_ACTIVE,
                    'metadata' => array_merge($archived->metadata ?? [], [
                        'tier_migration' => [
                            'from' => $oldTier,
                            'to' => $newTier,
                            'migrated_at' => now()->toIso8601String(),
                        ],
                    ]),
                ]);

                Log::info('Document tier migrated', [
                    'archived_document_id' => $archived->id,
                    'from_tier' => $oldTier,
                    'to_tier' => $newTier,
                ]);

                return $archived->fresh();

            } catch (\Exception $e) {
                // Revert status on failure
                $archived->update(['archive_status' => ArchivedDocument::STATUS_ACTIVE]);
                throw $e;
            }
        });
    }

    /**
     * Restore a document from cold/archive storage.
     */
    public function restore(ArchivedDocument $archived): Document
    {
        // Update last accessed timestamp
        $archived->update(['last_accessed_at' => now()]);

        // Get the original document
        $document = $archived->document;

        if (! $document) {
            throw new \RuntimeException('Original document not found');
        }

        // If in cold/archive tier, initiate restore (async for Glacier)
        if ($archived->archive_tier !== ArchivedDocument::TIER_HOT) {
            $this->initiateGlacierRestore($archived);
        }

        return $document;
    }

    /**
     * Verify the integrity of an archived document.
     */
    public function verifyIntegrity(ArchivedDocument $archived): array
    {
        $errors = [];
        $warnings = [];

        // 1. Verify document file hash
        try {
            $currentHash = $this->hashingService->hashDocument(
                $archived->effective_storage_path,
                $archived->storage_disk
            );

            $hashValid = hash_equals($archived->archive_hash, $currentHash);
            if (! $hashValid) {
                $errors[] = 'Document hash mismatch - file may have been altered';
            }
        } catch (\Exception $e) {
            $errors[] = 'Unable to verify document hash: '.$e->getMessage();
        }

        // 2. Verify TSA chain
        if ($archived->tsaChain) {
            $chainResult = $this->resealService->verifyChain($archived->tsaChain);
            if (! $chainResult->isValid) {
                $errors = array_merge($errors, $chainResult->errors);
            }
            $warnings = array_merge($warnings, $chainResult->warnings ?? []);
        } else {
            $warnings[] = 'No TSA chain found for verification';
        }

        // 3. Check retention status
        if ($archived->isRetentionExpired()) {
            $warnings[] = 'Document retention has expired';
        } elseif ($archived->isRetentionExpiring(90)) {
            $warnings[] = 'Document retention expiring within 90 days';
        }

        // 4. Check reseal status
        if ($archived->needsReseal()) {
            $warnings[] = 'Document needs TSA re-sealing';
        }

        // Update verification timestamp
        $archived->update(['last_verified_at' => now()]);

        $isValid = empty($errors);

        Log::info('Archive integrity verification completed', [
            'archived_document_id' => $archived->id,
            'is_valid' => $isValid,
            'errors_count' => count($errors),
            'warnings_count' => count($warnings),
        ]);

        return [
            'is_valid' => $isValid,
            'errors' => $errors,
            'warnings' => $warnings,
            'verified_at' => now()->toIso8601String(),
            'archive_hash' => $archived->archive_hash,
            'retention_expires_at' => $archived->retention_expires_at->toDateString(),
            'next_reseal_at' => $archived->next_reseal_at?->toDateString(),
        ];
    }

    /**
     * Get documents due for re-sealing.
     */
    public function getDocumentsDueForReseal(int $daysAhead = 30): Collection
    {
        return ArchivedDocument::query()
            ->dueForReseal($daysAhead)
            ->with(['document', 'tsaChain'])
            ->orderBy('next_reseal_at')
            ->get();
    }

    /**
     * Get documents ready for tier migration.
     */
    public function getDocumentsForTierMigration(): Collection
    {
        $hotToColdDays = config('archive.tier_migration.hot_to_cold_days', 365);
        $coldToArchiveDays = config('archive.tier_migration.cold_to_archive_days', 3650);

        // Hot to Cold
        $hotDocuments = ArchivedDocument::query()
            ->readyForTierMigration(ArchivedDocument::TIER_HOT, $hotToColdDays)
            ->get()
            ->map(fn ($doc) => ['document' => $doc, 'target_tier' => ArchivedDocument::TIER_COLD]);

        // Cold to Archive
        $coldDocuments = ArchivedDocument::query()
            ->readyForTierMigration(ArchivedDocument::TIER_COLD, $coldToArchiveDays)
            ->get()
            ->map(fn ($doc) => ['document' => $doc, 'target_tier' => ArchivedDocument::TIER_ARCHIVE]);

        return $hotDocuments->merge($coldDocuments);
    }

    /**
     * Process automatic tier migrations.
     */
    public function processTierMigrations(): array
    {
        $migrations = $this->getDocumentsForTierMigration();
        $results = [
            'total' => $migrations->count(),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($migrations as $migration) {
            try {
                $this->moveTier($migration['document'], $migration['target_tier']);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][$migration['document']->id] = $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * Get archive statistics.
     */
    public function getStatistics(?int $tenantId = null): array
    {
        $baseQuery = ArchivedDocument::query()->active();

        if ($tenantId) {
            $baseQuery->where('tenant_id', $tenantId);
        }

        $byTier = [
            'hot' => (clone $baseQuery)->inTier(ArchivedDocument::TIER_HOT)->count(),
            'cold' => (clone $baseQuery)->inTier(ArchivedDocument::TIER_COLD)->count(),
            'archive' => (clone $baseQuery)->inTier(ArchivedDocument::TIER_ARCHIVE)->count(),
        ];

        $resealStats = [
            'due_now' => (clone $baseQuery)->dueForReseal(0)->count(),
            'due_30_days' => (clone $baseQuery)->dueForReseal(30)->count(),
            'due_90_days' => (clone $baseQuery)->dueForReseal(90)->count(),
        ];

        $retentionStats = $this->policyService->getRetentionStats($tenantId);

        return [
            'total' => array_sum($byTier),
            'by_tier' => $byTier,
            'reseal' => $resealStats,
            'retention' => $retentionStats,
        ];
    }

    /**
     * Calculate archive hash including metadata.
     */
    private function calculateArchiveHash(Document $document): string
    {
        // Create a hash that includes document content hash and key metadata
        $data = implode('|', [
            $document->content_hash,
            $document->uuid,
            $document->original_name,
            $document->file_size,
            $document->created_at->toIso8601String(),
        ]);

        return $this->hashingService->hashString($data);
    }

    /**
     * Generate archive storage path for a document.
     */
    private function generateArchivePath(Document $document): string
    {
        $prefix = config('archive.storage.prefix', 'archive');

        return sprintf(
            '%s/%s/%s/%s_%s.pdf',
            $prefix,
            $document->tenant_id,
            now()->format('Y/m'),
            $document->uuid,
            Str::random(8)
        );
    }

    /**
     * Generate tier-specific storage path.
     */
    private function generateTierPath(ArchivedDocument $archived, string $tier): string
    {
        $prefix = config('archive.storage.prefix', 'archive');

        return sprintf(
            '%s/%s/%s/%s_%s.pdf',
            $prefix,
            $tier,
            $archived->tenant_id,
            $archived->uuid,
            Str::random(8)
        );
    }

    /**
     * Copy document to archive storage.
     */
    private function copyToArchiveStorage(Document $document, string $archivePath): void
    {
        $sourceDisk = $document->storage_disk ?? 'local';
        $targetDisk = config('archive.tiers.hot.storage_disk', 'local');

        $content = Storage::disk($sourceDisk)->get($document->stored_path);
        Storage::disk($targetDisk)->put($archivePath, $content, [
            'visibility' => 'private',
        ]);
    }

    /**
     * Copy document between storage tiers.
     */
    private function copyBetweenTiers(ArchivedDocument $archived, string $targetDisk, string $targetPath): void
    {
        $content = Storage::disk($archived->storage_disk)->get($archived->effective_storage_path);
        Storage::disk($targetDisk)->put($targetPath, $content, [
            'visibility' => 'private',
        ]);
    }

    /**
     * Initiate Glacier restore for cold/archive tier documents.
     */
    private function initiateGlacierRestore(ArchivedDocument $archived): void
    {
        $tierConfig = config("archive.tiers.{$archived->archive_tier}");
        $restoreHours = $tierConfig['restore_time_hours'] ?? 12;

        Log::info('Initiating Glacier restore', [
            'archived_document_id' => $archived->id,
            'tier' => $archived->archive_tier,
            'estimated_hours' => $restoreHours,
        ]);

        // In a real implementation, this would call S3 Glacier RestoreObject API
        // For now, we just log the request
    }
}
