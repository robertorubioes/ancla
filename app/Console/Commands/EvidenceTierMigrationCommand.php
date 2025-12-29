<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Archive\LongTermArchiveService;
use Illuminate\Console\Command;

/**
 * Command to migrate documents between storage tiers based on age policy.
 *
 * @example php artisan evidence:tier-migration --dry-run
 */
class EvidenceTierMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evidence:tier-migration
                            {--dry-run : Show what would be migrated without making changes}
                            {--tier= : Only migrate to a specific tier (cold, archive)}
                            {--limit=100 : Maximum number of documents to migrate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate archived documents between storage tiers based on retention policy';

    /**
     * Execute the console command.
     */
    public function handle(LongTermArchiveService $archiveService): int
    {
        $dryRun = $this->option('dry-run');
        $targetTier = $this->option('tier');
        $limit = (int) $this->option('limit');

        $this->info('📦 Storage Tier Migration Process');
        $this->line('==================================');
        $this->line('Dry run: '.($dryRun ? 'Yes' : 'No'));
        $this->line('Target tier: '.($targetTier ?: 'All'));
        $this->line("Limit: {$limit}");
        $this->newLine();

        // Display current tier statistics
        $stats = $archiveService->getStatistics();
        $this->info('Current Tier Distribution:');
        $this->line("  Hot:     {$stats['by_tier']['hot']} documents");
        $this->line("  Cold:    {$stats['by_tier']['cold']} documents");
        $this->line("  Archive: {$stats['by_tier']['archive']} documents");
        $this->newLine();

        // Get documents ready for migration
        $migrations = $archiveService->getDocumentsForTierMigration();

        // Filter by target tier if specified
        if ($targetTier) {
            $migrations = $migrations->filter(fn ($m) => $m['target_tier'] === $targetTier);
        }

        // Apply limit
        $migrations = $migrations->take($limit);

        if ($migrations->isEmpty()) {
            $this->info('✓ No documents require tier migration at this time.');

            return Command::SUCCESS;
        }

        $this->info("Found {$migrations->count()} document(s) ready for migration:");
        $this->newLine();

        // Display migrations table
        $tableData = $migrations->map(fn ($m) => [
            $m['document']->id,
            $m['document']->uuid,
            $m['document']->archive_tier,
            $m['target_tier'],
            $m['document']->archived_at->format('Y-m-d'),
            $m['document']->archived_at->diffInDays(now()).' days',
        ])->toArray();

        $this->table(
            ['ID', 'UUID', 'Current Tier', 'Target Tier', 'Archived Date', 'Age'],
            $tableData
        );

        if ($dryRun) {
            $this->warn('Dry run mode - no changes will be made.');

            return Command::SUCCESS;
        }

        // Confirm before proceeding
        if (! $this->confirm('Do you want to proceed with tier migration?')) {
            $this->warn('Operation cancelled.');

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info('Processing tier migrations...');

        $progressBar = $this->output->createProgressBar($migrations->count());
        $progressBar->start();

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($migrations as $migration) {
            try {
                $archiveService->moveTier($migration['document'], $migration['target_tier']);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][$migration['document']->id] = $e->getMessage();
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info('Migration Results:');
        $this->line("  ✓ Success: {$results['success']}");
        $this->line("  ✗ Failed: {$results['failed']}");

        if (! empty($results['errors'])) {
            $this->newLine();
            $this->error('Errors encountered:');
            foreach ($results['errors'] as $docId => $error) {
                $this->line("  Document {$docId}: {$error}");
            }
        }

        // Display updated statistics
        $this->newLine();
        $updatedStats = $archiveService->getStatistics();
        $this->info('Updated Tier Distribution:');
        $this->line("  Hot:     {$updatedStats['by_tier']['hot']} documents");
        $this->line("  Cold:    {$updatedStats['by_tier']['cold']} documents");
        $this->line("  Archive: {$updatedStats['by_tier']['archive']} documents");

        return $results['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
