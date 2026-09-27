<?php

namespace App\Console\Commands;

use App\Jobs\FetchGuildRoster as FetchGuildRosterJob;
use App\Jobs\FetchGuildRosters;
use Carbon\CarbonInterval;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;

#[Signature('fetch:blizzard-roster {--game-version= : Only refresh the roster for this game version ID}')]
#[Description('Refresh the guild roster from Blizzard API and update the cache.')]
class FetchGuildRoster extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $gameVersionId = $this->option('game-version') !== null ? (int) $this->option('game-version') : null;
        $rateLimiterKey = $gameVersionId !== null ? "fetch-guild-roster-job:{$gameVersionId}" : 'fetch-guild-roster-job';

        if (RateLimiter::tooManyAttempts($rateLimiterKey, 1)) {
            $retryAfter = CarbonInterval::seconds(RateLimiter::availableIn($rateLimiterKey))->cascade()->forHumans();

            $this->warn("The guild roster was refreshed recently. Please wait {$retryAfter} before refreshing again.");

            return;
        }

        if ($gameVersionId !== null) {
            FetchGuildRosterJob::dispatchSync($gameVersionId);

            $this->info('Guild roster refreshed.');

            return;
        }

        FetchGuildRosters::dispatchSync();

        $this->info('Guild roster refresh queued for each game version.');
    }
}
