<?php

use App\Jobs\CheckLevelCapAchievements;
use App\Jobs\ExportAddonDataJob;
use Illuminate\Support\Facades\Schedule;

Schedule::call('model:prune')->dailyAt('03:00');
Schedule::job(ExportAddonDataJob::class)->dailyAt('03:00');

/**
 * This job must not run.
 *
 * It was added to the list in case we need to add the reached_level_cap_at column back to the characters
 * table in the future, but for now it should be left commented out.
 */
// Schedule::job(CheckLevelCapAchievements::class)->everyFiveMinutes();
