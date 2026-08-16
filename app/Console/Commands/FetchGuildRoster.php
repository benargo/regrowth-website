<?php

namespace App\Console\Commands;

use App\Jobs\FetchGuildRoster as FetchGuildRosterJob;
use Carbon\CarbonInterval;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;

#[Signature('fetch:blizzard-roster')]
#[Description('Refresh the guild roster from Blizzard API and update the cache.')]
class FetchGuildRoster extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (RateLimiter::tooManyAttempts('fetch-guild-roster-job', 1)) {
            $retryAfter = CarbonInterval::seconds(RateLimiter::availableIn('fetch-guild-roster-job'))->cascade()->forHumans();

            $this->warn("The guild roster was refreshed recently. Please wait {$retryAfter} before refreshing again.");

            return;
        }

        FetchGuildRosterJob::dispatchSync();

        $this->info('Guild roster refreshed.');
    }
}
