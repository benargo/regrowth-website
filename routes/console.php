<?php

use App\Jobs\BuildAddonDataFile;
use App\Jobs\CheckLevelCapAchievements;
use App\Jobs\SyncDiscordRoles;
use Illuminate\Support\Facades\Schedule;

/**
 * Sync Discord roles daily at 1:00 AM server time.
 */
Schedule::job(SyncDiscordRoles::class)->dailyAt('01:00')->name('sync-discord-roles');

/**
 * Reset daily quests at 3:00 AM server time.
 */
Schedule::call('discord:reset-daily-quests')
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

/**
 * This job must not run.
 *
 * It was added to the list in case we need to add the reached_level_cap_at column back to the characters
 * table in the future, but for now it should be left commented out.
 */
// Schedule::job(CheckLevelCapAchievements::class)->everyFiveMinutes();
