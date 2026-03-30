<?php

namespace App\Console\Commands;

use App\Services\Blizzard\GuildService;
use Illuminate\Console\Command;

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
    public function handle(GuildService $guildService): void
    {
        if ($guildService->hasRosterCache()) {
            $this->error('The guild roster was fetched recently. Please wait for the cache to expire.');

            return;
        }

        $guildService->roster();
        $this->info('Guild roster refreshed.');
    }
}
