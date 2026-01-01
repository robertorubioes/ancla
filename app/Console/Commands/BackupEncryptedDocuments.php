<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\SignedDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Backup Encrypted Documents Command
 *
 * Creates backups of encrypted documents for disaster recovery.
 * Backups include both the encrypted files and metadata.
 *
 * Scheduled to run daily at 2 AM (configurable in routes/console.php).
 *
 * Usage:
 * - Manual: php artisan documents:backup
 * - Test: php artisan documents:backup --dry-run
 *
 * @see docs/architecture/adr-010-encryption-at-rest.md
 */
class BackupEncryptedDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:backup
                            {--dry-run : Simulate backup without saving}
                            {--tenant= : Only backup documents for specific tenant ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create backup of encrypted documents for disaster recovery';

    /**
     * Statistics counters.
     */
    private int $documentsBackedUp = 0;

    private int $signedDocumentsBackedUp = 0;

    private int $errors = 0;

    private int $totalSize = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('encryption.backup.enabled', true)) {
            $this->warn('⚠️  Backup is disabled in configuration');

            return self::SUCCESS;
        }

        $isDryRun = $this->option('dry-run');
        $tenantId = $this->option('tenant');

        $this->info('💾 Starting Encrypted Documents Backup');
        $this->newLine();

        if ($isDryRun) {
            $this->warn('⚠️  DRY RUN MODE - No backups will be created');
            $this->newLine();
        }

        // Create backup timestamp
        $timestamp = now()->format('Y-m-d_His');
        $backupPath = $this->getBackupPath($timestamp);

        $this->info("📁 Backup location: {$backupPath}");
        $this->newLine();

        try {
            // Backup documents
            $this->info('📄 Backing up Documents...');
            $this->backupDocuments($backupPath, $isDryRun, $tenantId);

            $this->newLine();

            // Backup signed documents
            $this->info('✍️  Backing up SignedDocuments...');
            $this->backupSignedDocuments($backupPath, $isDryRun, $tenantId);

            $this->newLine();

            // Create backup manifest
            if (! $isDryRun) {
                $this->createManifest($backupPath, $timestamp, $tenantId);
            }

            // Display summary
            $this->displaySummary($isDryRun);

            // Cleanup old backups
            if (! $isDryRun) {
                $this->cleanupOldBackups();
            }

            Log::info('Encrypted documents backup completed', [
                'documents' => $this->documentsBackedUp,
                'signed_documents' => $this->signedDocumentsBackedUp,
                'total_size_mb' => round($this->totalSize / 1024 / 1024, 2),
                'errors' => $this->errors,
            ]);

            return $this->errors > 0 ? self::FAILURE : self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("❌ Backup failed: {$e->getMessage()}");
            Log::error('Backup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Backup documents table.
     */
    private function backupDocuments(string $backupPath, bool $isDryRun, ?string $tenantId): void
    {
        $query = Document::withoutGlobalScopes()
            ->where('is_encrypted', true);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $documents = $query->get();
        $total = $documents->count();

        $this->info("Found {$total} encrypted documents");

        if ($total === 0) {
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($documents as $document) {
            try {
                if ($document->file_path && Storage::exists($document->file_path)) {
                    $fileContent = Storage::get($document->file_path);
                    $fileSize = strlen($fileContent);

                    if (! $isDryRun) {
                        $backupDisk = config('encryption.backup.disk', 's3');
                        $backupFile = "{$backupPath}/documents/{$document->tenant_id}/{$document->id}.encrypted";
                        Storage::disk($backupDisk)->put($backupFile, $fileContent);
                    }

                    $this->documentsBackedUp++;
                    $this->totalSize += $fileSize;
                }

                $bar->advance();
            } catch (\Exception $e) {
                $this->errors++;
                Log::error('Failed to backup document', [
                    'document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Backup signed documents table.
     */
    private function backupSignedDocuments(string $backupPath, bool $isDryRun, ?string $tenantId): void
    {
        $query = SignedDocument::withoutGlobalScopes()
            ->where('is_encrypted', true);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $documents = $query->get();
        $total = $documents->count();

        $this->info("Found {$total} encrypted signed documents");

        if ($total === 0) {
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($documents as $document) {
            try {
                if ($document->signed_file_path && Storage::exists($document->signed_file_path)) {
                    $fileContent = Storage::get($document->signed_file_path);
                    $fileSize = strlen($fileContent);

                    if (! $isDryRun) {
                        $backupDisk = config('encryption.backup.disk', 's3');
                        $backupFile = "{$backupPath}/signed_documents/{$document->tenant_id}/{$document->id}.encrypted";
                        Storage::disk($backupDisk)->put($backupFile, $fileContent);
                    }

                    $this->signedDocumentsBackedUp++;
                    $this->totalSize += $fileSize;
                }

                $bar->advance();
            } catch (\Exception $e) {
                $this->errors++;
                Log::error('Failed to backup signed document', [
                    'signed_document_id' => $document->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Create backup manifest file.
     */
    private function createManifest(string $backupPath, string $timestamp, ?string $tenantId): void
    {
        $manifest = [
            'backup_date' => $timestamp,
            'backup_version' => '1.0',
            'encryption_key_version' => config('encryption.key_version', 'v1'),
            'tenant_id' => $tenantId,
            'documents_backed_up' => $this->documentsBackedUp,
            'signed_documents_backed_up' => $this->signedDocumentsBackedUp,
            'total_size_bytes' => $this->totalSize,
            'total_size_mb' => round($this->totalSize / 1024 / 1024, 2),
            'errors' => $this->errors,
            'created_at' => now()->toIso8601String(),
        ];

        $backupDisk = config('encryption.backup.disk', 's3');
        Storage::disk($backupDisk)->put(
            "{$backupPath}/manifest.json",
            json_encode($manifest, JSON_PRETTY_PRINT)
        );

        $this->info('📋 Backup manifest created');
    }

    /**
     * Get backup path for this run.
     */
    private function getBackupPath(string $timestamp): string
    {
        $basePath = config('encryption.backup.path', 'backups/encrypted');

        return "{$basePath}/{$timestamp}";
    }

    /**
     * Cleanup old backups based on retention policy.
     */
    private function cleanupOldBackups(): void
    {
        $retentionDays = config('encryption.backup.retention_days', 30);
        $cutoffDate = now()->subDays($retentionDays);

        $this->info("🧹 Cleaning up backups older than {$retentionDays} days...");

        try {
            $backupDisk = config('encryption.backup.disk', 's3');
            $basePath = config('encryption.backup.path', 'backups/encrypted');

            $directories = Storage::disk($backupDisk)->directories($basePath);
            $deleted = 0;

            foreach ($directories as $directory) {
                // Extract timestamp from directory name
                $dirName = basename($directory);

                // Try to parse directory name as date
                try {
                    $backupDate = \Carbon\Carbon::createFromFormat('Y-m-d_His', $dirName);

                    if ($backupDate->lt($cutoffDate)) {
                        Storage::disk($backupDisk)->deleteDirectory($directory);
                        $deleted++;
                    }
                } catch (\Exception $e) {
                    // Skip directories that don't match expected format
                    continue;
                }
            }

            if ($deleted > 0) {
                $this->info("Deleted {$deleted} old backup(s)");
            } else {
                $this->info('No old backups to delete');
            }
        } catch (\Exception $e) {
            $this->warn("Failed to cleanup old backups: {$e->getMessage()}");
            Log::warning('Backup cleanup failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Display summary statistics.
     */
    private function displaySummary(bool $isDryRun): void
    {
        $this->info('📊 Backup Summary');
        $this->newLine();

        $sizeMB = round($this->totalSize / 1024 / 1024, 2);

        $this->table(
            ['Metric', 'Count'],
            [
                ['Documents backed up', $this->documentsBackedUp],
                ['Signed documents backed up', $this->signedDocumentsBackedUp],
                ['Total size', "{$sizeMB} MB"],
                ['Errors', $this->errors],
            ]
        );

        $this->newLine();

        if ($isDryRun) {
            $this->info('✅ Dry run completed. No backups were created.');
        } else {
            if ($this->errors > 0) {
                $this->error("⚠️  Completed with {$this->errors} errors. Check logs for details.");
            } else {
                $total = $this->documentsBackedUp + $this->signedDocumentsBackedUp;
                $this->info("✅ Successfully backed up {$total} encrypted documents ({$sizeMB} MB).");
            }
        }
    }
}
