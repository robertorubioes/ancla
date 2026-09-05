<?php

namespace App\Console\Commands;

use App\Exceptions\EncryptionException;
use App\Models\Document;
use App\Models\SignedDocument;
use App\Services\Document\DocumentEncryptionService;
use App\Services\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Encrypt Existing Documents Command
 *
 * Encrypts all existing documents that are not yet encrypted.
 * Processes documents in batches to avoid memory issues.
 *
 * Usage:
 * - Dry run: php artisan documents:encrypt-existing --dry-run
 * - Actual: php artisan documents:encrypt-existing
 * - Specific tenant: php artisan documents:encrypt-existing --tenant=123
 * - Custom batch size: php artisan documents:encrypt-existing --batch=50
 *
 * @see docs/architecture/adr-010-encryption-at-rest.md
 */
class EncryptExistingDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:encrypt-existing
                            {--dry-run : Simulate encryption without saving}
                            {--tenant= : Only encrypt documents for specific tenant ID}
                            {--batch=100 : Number of documents to process per batch}
                            {--force : Force encryption even if already encrypted}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt all existing documents that are not yet encrypted';

    /**
     * Statistics counters.
     */
    private int $documentsProcessed = 0;

    private int $documentsEncrypted = 0;

    private int $documentsSkipped = 0;

    private int $documentsErrored = 0;

    private int $signedDocumentsProcessed = 0;

    private int $signedDocumentsEncrypted = 0;

    private int $signedDocumentsSkipped = 0;

    private int $signedDocumentsErrored = 0;

    public function __construct(
        private readonly DocumentEncryptionService $encryptionService,
        private readonly TenantContext $tenantContext
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $tenantId = $this->option('tenant');
        $batchSize = (int) $this->option('batch');
        $force = $this->option('force');

        $this->info('🔐 Starting Document Encryption Process');
        $this->newLine();

        if ($isDryRun) {
            $this->warn('⚠️  DRY RUN MODE - No changes will be saved');
            $this->newLine();
        }

        if ($tenantId) {
            $this->info("📍 Filtering to tenant ID: {$tenantId}");
            $this->newLine();
        }

        // Process Documents
        $this->info('📄 Processing Documents table...');
        $this->processDocuments($isDryRun, $tenantId, $batchSize, $force);

        $this->newLine();

        // Process SignedDocuments
        $this->info('✍️  Processing SignedDocuments table...');
        $this->processSignedDocuments($isDryRun, $tenantId, $batchSize, $force);

        $this->newLine();

        // Display summary
        $this->displaySummary($isDryRun);

        // Determine exit code
        if ($this->documentsErrored > 0 || $this->signedDocumentsErrored > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Process Documents table.
     */
    private function processDocuments(bool $isDryRun, ?string $tenantId, int $batchSize, bool $force): void
    {
        $query = Document::withoutGlobalScopes();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if (! $force) {
            $query->where(function ($q) {
                $q->where('is_encrypted', false)
                    ->orWhereNull('is_encrypted');
            });
        }

        $total = $query->count();
        $this->info("Found {$total} documents to process");

        if ($total === 0) {
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk($batchSize, function ($documents) use ($isDryRun, $bar) {
            foreach ($documents as $document) {
                $this->processDocument($document, $isDryRun);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    /**
     * Process a single document.
     */
    private function processDocument(Document $document, bool $isDryRun): void
    {
        $this->documentsProcessed++;

        try {
            // Set tenant context
            $this->tenantContext->run($document->tenant, function () use ($document, $isDryRun) {
                // Check if content needs encryption
                if (! $document->file_path) {
                    $this->documentsSkipped++;

                    return;
                }

                // Read file content
                $content = \Storage::get($document->file_path);
                if (! $content) {
                    $this->documentsSkipped++;

                    return;
                }

                // Check if already encrypted
                if ($this->encryptionService->isEncrypted($content)) {
                    $this->documentsSkipped++;

                    return;
                }

                // Encrypt content
                if (! $isDryRun) {
                    $encrypted = $this->encryptionService->encrypt($content);
                    \Storage::put($document->file_path, $encrypted);

                    // Update metadata
                    DB::table('documents')
                        ->where('id', $document->id)
                        ->update([
                            'is_encrypted' => true,
                            'encrypted_at' => now(),
                            'encryption_key_version' => config('encryption.key_version', 'v1'),
                            'updated_at' => now(),
                        ]);
                }

                $this->documentsEncrypted++;
            });
        } catch (EncryptionException $e) {
            $this->documentsErrored++;
            Log::error('Failed to encrypt document', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            if ($this->output->isVerbose()) {
                $this->error("Failed to encrypt document {$document->id}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Process SignedDocuments table.
     */
    private function processSignedDocuments(bool $isDryRun, ?string $tenantId, int $batchSize, bool $force): void
    {
        $query = SignedDocument::withoutGlobalScopes();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if (! $force) {
            $query->where(function ($q) {
                $q->where('is_encrypted', false)
                    ->orWhereNull('is_encrypted');
            });
        }

        $total = $query->count();
        $this->info("Found {$total} signed documents to process");

        if ($total === 0) {
            return;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk($batchSize, function ($documents) use ($isDryRun, $bar) {
            foreach ($documents as $document) {
                $this->processSignedDocument($document, $isDryRun);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
    }

    /**
     * Process a single signed document.
     */
    private function processSignedDocument(SignedDocument $document, bool $isDryRun): void
    {
        $this->signedDocumentsProcessed++;

        try {
            // Set tenant context
            $this->tenantContext->run($document->tenant, function () use ($document, $isDryRun) {
                // Check if content needs encryption
                if (! $document->signed_file_path) {
                    $this->signedDocumentsSkipped++;

                    return;
                }

                // Read file content
                $content = \Storage::get($document->signed_file_path);
                if (! $content) {
                    $this->signedDocumentsSkipped++;

                    return;
                }

                // Check if already encrypted
                if ($this->encryptionService->isEncrypted($content)) {
                    $this->signedDocumentsSkipped++;

                    return;
                }

                // Encrypt content
                if (! $isDryRun) {
                    $encrypted = $this->encryptionService->encrypt($content);
                    \Storage::put($document->signed_file_path, $encrypted);

                    // Update metadata
                    DB::table('signed_documents')
                        ->where('id', $document->id)
                        ->update([
                            'is_encrypted' => true,
                            'encrypted_at' => now(),
                            'encryption_key_version' => config('encryption.key_version', 'v1'),
                            'updated_at' => now(),
                        ]);
                }

                $this->signedDocumentsEncrypted++;
            });
        } catch (EncryptionException $e) {
            $this->signedDocumentsErrored++;
            Log::error('Failed to encrypt signed document', [
                'signed_document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);

            if ($this->output->isVerbose()) {
                $this->error("Failed to encrypt signed document {$document->id}: {$e->getMessage()}");
            }
        }
    }

    /**
     * Display summary statistics.
     */
    private function displaySummary(bool $isDryRun): void
    {
        $this->info('📊 Encryption Summary');
        $this->newLine();

        // Documents table
        $this->table(
            ['Metric', 'Documents', 'Signed Documents'],
            [
                ['Processed', $this->documentsProcessed, $this->signedDocumentsProcessed],
                ['Encrypted', $this->documentsEncrypted, $this->signedDocumentsEncrypted],
                ['Skipped', $this->documentsSkipped, $this->signedDocumentsSkipped],
                ['Errors', $this->documentsErrored, $this->signedDocumentsErrored],
            ]
        );

        $this->newLine();

        $totalEncrypted = $this->documentsEncrypted + $this->signedDocumentsEncrypted;
        $totalErrors = $this->documentsErrored + $this->signedDocumentsErrored;

        if ($isDryRun) {
            $this->info("✅ Dry run completed. {$totalEncrypted} documents would be encrypted.");
            $this->info('💡 Run without --dry-run to perform actual encryption.');
        } else {
            if ($totalErrors > 0) {
                $this->error("⚠️  Completed with {$totalErrors} errors. Check logs for details.");
            } else {
                $this->info("✅ Successfully encrypted {$totalEncrypted} documents.");
            }
        }
    }
}
