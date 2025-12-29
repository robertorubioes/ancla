<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Archive\RetentionPolicyService;
use Illuminate\Console\Command;

/**
 * Command to process or clean up documents with expired retention.
 *
 * @example php artisan evidence:cleanup-expired --dry-run
 */
class EvidenceCleanupExpiredCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evidence:cleanup-expired
                            {--dry-run : Show what would be cleaned up without making changes}
                            {--force : Skip confirmation prompts}
                            {--tenant= : Only process documents for a specific tenant}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process documents with expired retention according to their policy actions';

    /**
     * Execute the console command.
     */
    public function handle(RetentionPolicyService $policyService): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $tenantId = $this->option('tenant') ? (int) $this->option('tenant') : null;

        $this->info('🗑️  Retention Expiry Processing');
        $this->line('================================');
        $this->line('Dry run: '.($dryRun ? 'Yes' : 'No'));
        $this->line('Tenant: '.($tenantId ?: 'All'));
        $this->newLine();

        // Get retention statistics
        $stats = $policyService->getRetentionStats($tenantId);

        $this->info('Retention Status Overview:');
        $this->line("  Total archived:     {$stats['total_archived']}");
        $this->line("  Expired:            {$stats['expired']}");
        $this->line("  Expiring (30 days): {$stats['expiring_30_days']}");
        $this->line("  Expiring (90 days): {$stats['expiring_90_days']}");
        $this->line("  Healthy:            {$stats['healthy']} ({$stats['percentage_healthy']}%)");
        $this->newLine();

        // Get expired documents
        $expired = $policyService->getExpiredDocuments($tenantId);

        if ($expired->isEmpty()) {
            $this->info('✓ No documents with expired retention found.');

            return Command::SUCCESS;
        }

        $this->warn("Found {$expired->count()} document(s) with expired retention:");
        $this->newLine();

        // Group by action type
        $byAction = $expired->groupBy(function ($doc) {
            $policy = $doc->retentionPolicy;

            return $policy ? $policy->on_expiry_action : 'notify';
        });

        foreach ($byAction as $action => $documents) {
            $this->line("  {$action}: {$documents->count()} documents");
        }
        $this->newLine();

        // Display detailed table
        $tableData = $expired->take(20)->map(fn ($doc) => [
            $doc->id,
            $doc->uuid,
            $doc->document_id,
            $doc->retention_expires_at->format('Y-m-d'),
            $doc->retention_expires_at->diffInDays(now()).' days ago',
            $doc->retentionPolicy?->on_expiry_action ?? 'notify',
        ])->toArray();

        $this->table(
            ['ID', 'UUID', 'Doc ID', 'Expired On', 'Age', 'Action'],
            $tableData
        );

        if ($expired->count() > 20) {
            $this->line('... and '.($expired->count() - 20).' more documents');
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Dry run mode - no changes will be made.');
            $this->newLine();
            $this->info('Actions that would be taken:');
            foreach ($byAction as $action => $documents) {
                $this->line("  - {$action}: {$documents->count()} documents");
            }

            return Command::SUCCESS;
        }

        // Confirm before proceeding (unless forced)
        if (! $force) {
            $this->newLine();
            $this->warn('⚠️  WARNING: This operation may permanently delete or archive documents.');
            $this->warn('   Documents marked for deletion cannot be recovered.');
            $this->newLine();

            if (! $this->confirm('Do you want to proceed with processing expired documents?')) {
                $this->warn('Operation cancelled.');

                return Command::SUCCESS;
            }

            // Double confirm for delete actions
            if ($byAction->has('delete') && $byAction['delete']->count() > 0) {
                $deleteCount = $byAction['delete']->count();
                $this->newLine();
                $this->error("⚠️  {$deleteCount} document(s) are marked for PERMANENT DELETION.");

                if (! $this->confirm("Are you ABSOLUTELY SURE you want to delete these {$deleteCount} documents?")) {
                    $this->warn('Operation cancelled.');

                    return Command::SUCCESS;
                }
            }
        }

        $this->newLine();
        $this->info('Processing expired documents...');

        $progressBar = $this->output->createProgressBar($expired->count());
        $progressBar->start();

        // Process expiry actions
        $results = $policyService->processExpiryActions($tenantId);

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info('Processing Results:');
        $this->line("  Total processed: {$results['processed']}");
        $this->line("  Archived:        {$results['archived']}");
        $this->line("  Deleted:         {$results['deleted']}");
        $this->line("  Extended:        {$results['extended']}");
        $this->line("  Notified:        {$results['notified']}");

        if (! empty($results['errors'])) {
            $this->newLine();
            $this->error('Errors encountered:');
            foreach ($results['errors'] as $docId => $error) {
                $this->line("  Document {$docId}: {$error}");
            }
        }

        return empty($results['errors']) ? Command::SUCCESS : Command::FAILURE;
    }
}
