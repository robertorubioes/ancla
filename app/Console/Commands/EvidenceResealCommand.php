<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Archive\TsaResealService;
use Illuminate\Console\Command;

/**
 * Command to re-seal TSA chains that are due for renewal.
 *
 * @example php artisan evidence:reseal --days-ahead=30
 */
class EvidenceResealCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'evidence:reseal
                            {--days-ahead=0 : Re-seal chains due within this many days}
                            {--dry-run : Show what would be resealed without making changes}
                            {--verify : Also verify all chains after resealing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-seal TSA chains that are due for renewal to maintain long-term validity';

    /**
     * Execute the console command.
     */
    public function handle(TsaResealService $resealService): int
    {
        $daysAhead = (int) $this->option('days-ahead');
        $dryRun = $this->option('dry-run');
        $verify = $this->option('verify');

        $this->info('🔐 TSA Chain Re-sealing Process');
        $this->line('================================');
        $this->line("Days ahead: {$daysAhead}");
        $this->line('Dry run: '.($dryRun ? 'Yes' : 'No'));
        $this->newLine();

        // Get chains due for reseal
        $chains = $resealService->getChainsDueForReseal($daysAhead);

        if ($chains->isEmpty()) {
            $this->info('✓ No TSA chains require re-sealing at this time.');

            return Command::SUCCESS;
        }

        $this->info("Found {$chains->count()} chain(s) due for re-sealing:");
        $this->newLine();

        // Display chains table
        $tableData = $chains->map(fn ($chain) => [
            $chain->id,
            $chain->uuid,
            $chain->document_id,
            $chain->seal_count,
            $chain->next_seal_due_at?->format('Y-m-d'),
            $chain->verification_status,
        ])->toArray();

        $this->table(
            ['ID', 'UUID', 'Document ID', 'Seal Count', 'Due Date', 'Status'],
            $tableData
        );

        if ($dryRun) {
            $this->warn('Dry run mode - no changes will be made.');

            return Command::SUCCESS;
        }

        // Confirm before proceeding
        if (! $this->confirm('Do you want to proceed with re-sealing?')) {
            $this->warn('Operation cancelled.');

            return Command::SUCCESS;
        }

        $this->newLine();
        $this->info('Processing re-seals...');

        $progressBar = $this->output->createProgressBar($chains->count());
        $progressBar->start();

        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($chains as $chain) {
            try {
                $resealService->reseal($chain);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][$chain->id] = $e->getMessage();
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display results
        $this->info('Re-sealing Results:');
        $this->line("  ✓ Success: {$results['success']}");
        $this->line("  ✗ Failed: {$results['failed']}");

        if (! empty($results['errors'])) {
            $this->newLine();
            $this->error('Errors encountered:');
            foreach ($results['errors'] as $chainId => $error) {
                $this->line("  Chain {$chainId}: {$error}");
            }
        }

        // Verify chains if requested
        if ($verify) {
            $this->newLine();
            $this->info('Verifying all active chains...');

            $verifyResults = $resealService->verifyAllChains();

            $this->line("  Total verified: {$verifyResults['total']}");
            $this->line("  ✓ Valid: {$verifyResults['valid']}");
            $this->line("  ✗ Invalid: {$verifyResults['invalid']}");
        }

        return $results['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
