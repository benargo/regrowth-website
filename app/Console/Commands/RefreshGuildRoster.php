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
        $guildService->fresh()->roster();
    }
}
