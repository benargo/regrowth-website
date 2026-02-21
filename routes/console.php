<?php

use App\Jobs\BuildAddonDataFile;
use App\Jobs\SyncDiscordRoles;
use Illuminate\Support\Facades\Schedule;

/**
 * Synchronise Discord roles every hour to ensure we have the latest roles and permissions.
 */
Schedule::job(SyncDiscordRoles::class)->hourly()->name('sync-discord-roles');

/**
 * Reset daily quests at 3:00 AM server time.
 */
Schedule::call('app:reset-daily-quests')
    ->dailyAt('03:00')
    ->name('reset-daily-quests')
    ->withoutOverlapping();

/**
 * Build the addon data file daily at 3:15 AM server time.
 *
 * This is a resource-intensive job, so we run it after the 3am job to avoid overloading the server.
 */
Schedule::job(BuildAddonDataFile::class)->dailyAt('03:15');

/**
 * This should be the last job to run each day.
 */
Schedule::call('model:prune')->dailyAt('06:00')->name('model-prune');
