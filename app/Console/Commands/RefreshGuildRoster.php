<?php

namespace App\Console\Commands;

use App\Services\Blizzard\BlizzardService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshGuildRoster extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:refresh-guild-roster';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh the guild roster from Blizzard API and update the cache.';

    /**
     * Execute the console command.
     */
    public function handle(BlizzardService $blizzard): void
    {
        if (Cache::tags(['blizzard', 'blizzard-api-response'])->has($blizzard->cacheKey('getGuildRoster'))) {
            $this->error('The guild roster was fetched recently. Please wait for the cache to expire.');

            return;
        }

        $blizzard->getGuildRoster();
        $this->info('Guild roster refreshed.');
    }
}
