<?php

use App\Jobs\BuildAddonDataFile;
use Illuminate\Support\Facades\Schedule;

/**
 * Synchronise Discord roles every hour to ensure we have the latest roles and permissions.
 */
Schedule::command('app:sync-discord')->hourly()->name('sync-discord')->withoutOverlapping();

/**
 * Refresh Warcraft Logs reports every 6 hours to keep the data up to date.
 */
Schedule::command('app:refresh-warcraft-logs-reports --latest')->everySixHours()->name('refresh-warcraft-logs-reports')->withoutOverlapping();

/**
 * Reset daily quests at 3:00 AM server time.
 */
Schedule::command('app:reset-daily-quests')
    ->dailyAt('03:00')
    ->name('reset-daily-quests')
    ->withoutOverlapping();

/**
 * Build the addon data file daily at 3:15 AM server time.
 *
 * This is a resource-intensive job, so we run it after the 3am job to avoid overloading the server.
 */
Schedule::job(BuildAddonDataFile::class)->dailyAt('03:15')->name('build-addon-data-file')->withoutOverlapping();

/**
 * This should be the last job to run each day.
 */
Schedule::command('model:prune')->dailyAt('06:00')->name('model-prune');
