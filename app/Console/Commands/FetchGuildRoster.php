<?php

namespace App\Console\Commands;

use App\Jobs\FetchGuildRoster as FetchGuildRosterJob;
use App\Jobs\FetchGuildRosters;
use App\Models\GameVersion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fetch:blizzard-roster {--game-version= : Only refresh the roster for this game version ID}')]
#[Description('Refresh the guild roster from Blizzard API and update the cache.')]
class FetchGuildRoster extends Command
{
    public function handle(): int
    {
        $gameVersionOption = $this->option('game-version');

        if ($gameVersionOption === null) {
            FetchGuildRosters::dispatchSync(bypassRateLimit: true);

            $this->info('Guild roster refresh queued for each game version.');

            return self::SUCCESS;
        }

        if (! ctype_digit((string) $gameVersionOption)) {
            $this->error('The --game-version option must be a numeric game version ID.');

            return self::FAILURE;
        }

        $gameVersion = GameVersion::find((int) $gameVersionOption);

        if ($gameVersion === null) {
            $this->error("Game version {$gameVersionOption} not found.");

            return self::FAILURE;
        }

        if ($gameVersion->realm === null || $gameVersion->blizzard_namespace === null) {
            $this->error("Game version {$gameVersion->id} has no realm or Blizzard namespace configured.");

            return self::FAILURE;
        }

        FetchGuildRosterJob::dispatchSync($gameVersion->id, bypassRateLimit: true);

        $this->info('Guild roster refreshed.');

        return self::SUCCESS;
    }
}
