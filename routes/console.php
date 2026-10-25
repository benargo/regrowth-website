<?php

use App\Jobs\BuildAddonExportFile;
use App\Jobs\Discord\RefreshUserAvatars;
use App\Jobs\Discord\SyncRoles as SyncDiscordRoles;
use App\Jobs\Discord\SyncUsers as SyncDiscordUsers;
use App\Jobs\FetchGuildRosters;
use App\Jobs\RaidHelper\FetchEvents as FetchRaidHelperEvents;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schedule;

/**
 * Synchronise Discord roles, then every user's profile and roles, every hour.
 */
Schedule::call(fn () => Bus::chain([new SyncDiscordRoles, new SyncDiscordUsers])->dispatch())
    ->hourly()
    ->name('sync-discord');

/**
 * Store Discord's default avatars and re-download every user's avatars daily.
 */
Schedule::job(new RefreshUserAvatars)->daily()->name('refresh-user-avatars')->withoutOverlapping();

/**
 * Reconcile events with the Raid Helper API every six hours as a backstop for missed webhooks.
 *
 * This job will run with default parameters, which means it will fetch events from the channels specified in the config file
 * and with a default time range of 1 week ago to 1 week from now.
 */
Schedule::job(new FetchRaidHelperEvents)->everySixHours()->name('fetch-raid-helper-events')->withoutOverlapping();

/**
 * Fetch the guild roster for each game version every 6 hours to ensure we have the latest member information.
 */
Schedule::job(new FetchGuildRosters)->everySixHours()->name('fetch-guild-rosters')->withoutOverlapping();

/**
 * Refresh Warcraft Logs reports every evening to keep the data up to date.
 *
 * The twiceDailyAt arguments indicate this job will run at 16:30 and 23:30 every day.
 */
Schedule::command('fetch:warcraft-logs --latest')
    ->twiceDailyAt(16, 23, 30)
    ->name('fetch-warcraft-logs-latest');

/**
 * Export the Regrowth Addon data daily at 3:15 AM server time.
 *
 * This is a resource-intensive job, so we run it after the 3am job to avoid overloading the server.
 */
Schedule::job(new BuildAddonExportFile)->dailyAt('03:15')->name('build-addon-export-file');

/**
 * This should be the last job to run each day.
 */
// Schedule::command('model:prune')->dailyAt('06:00')->name('model-prune');
