<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Schedule encrypted document backups.
 *
 * Runs daily at 2 AM to backup encrypted documents for disaster recovery.
 * Configure schedule in config/encryption.php
 *
 * @see docs/architecture/adr-010-encryption-at-rest.md
 */
Schedule::command('documents:backup')
    ->cron(config('encryption.backup.schedule', '0 2 * * *'))
    ->when(fn () => config('encryption.backup.enabled', true))
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();
