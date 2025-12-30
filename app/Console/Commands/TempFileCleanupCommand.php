<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to clean up temporary ZIP files from bundle downloads.
 *
 * These files should normally be deleted immediately after download,
 * but this command acts as a safety net for orphaned files.
 */
class TempFileCleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:temp-files
                            {--age=1 : Age in hours for files to be considered old}
                            {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up temporary ZIP files older than specified age';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $ageInHours = (int) $this->option('age');
        $dryRun = $this->option('dry-run');

        $tempDir = storage_path('app/temp');

        // Check if temp directory exists
        if (! is_dir($tempDir)) {
            $this->info('Temp directory does not exist. Nothing to clean up.');

            return Command::SUCCESS;
        }

        $this->info("Scanning for temporary files older than {$ageInHours} hour(s)...");

        $cutoffTime = time() - ($ageInHours * 3600);
        $deletedCount = 0;
        $deletedSize = 0;
        $errors = [];

        // Scan for bundle ZIP files
        $files = glob($tempDir.'/bundle_*.zip');

        if (empty($files)) {
            $this->info('No temporary files found.');

            return Command::SUCCESS;
        }

        $this->info('Found '.count($files).' temporary file(s).');

        foreach ($files as $file) {
            try {
                $fileTime = filemtime($file);
                $fileSize = filesize($file);

                if ($fileTime === false || $fileSize === false) {
                    $errors[] = "Could not read file info: {$file}";

                    continue;
                }

                // Check if file is old enough
                if ($fileTime < $cutoffTime) {
                    $age = round((time() - $fileTime) / 3600, 1);
                    $sizeKB = round($fileSize / 1024, 2);

                    if ($dryRun) {
                        $this->line('Would delete: '.basename($file)." (age: {$age}h, size: {$sizeKB}KB)");
                    } else {
                        if (unlink($file)) {
                            $this->line('Deleted: '.basename($file)." (age: {$age}h, size: {$sizeKB}KB)");
                            $deletedCount++;
                            $deletedSize += $fileSize;

                            Log::info('Temporary file cleaned up', [
                                'file' => basename($file),
                                'age_hours' => $age,
                                'size_bytes' => $fileSize,
                            ]);
                        } else {
                            $errors[] = "Failed to delete: {$file}";
                        }
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Error processing {$file}: ".$e->getMessage();
                Log::error('Error during temp file cleanup', [
                    'file' => $file,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Display summary
        $this->newLine();

        if ($dryRun) {
            $this->info("Dry run completed. Would have deleted {$deletedCount} file(s).");
        } else {
            if ($deletedCount > 0) {
                $totalSizeMB = round($deletedSize / 1024 / 1024, 2);
                $this->info("Cleanup completed. Deleted {$deletedCount} file(s), freed {$totalSizeMB}MB.");

                Log::info('Temp file cleanup completed', [
                    'deleted_count' => $deletedCount,
                    'freed_bytes' => $deletedSize,
                ]);
            } else {
                $this->info('No old files to clean up.');
            }
        }

        // Display errors if any
        if (! empty($errors)) {
            $this->newLine();
            $this->error('Encountered '.count($errors).' error(s):');

            foreach ($errors as $error) {
                $this->line('  - '.$error);
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
