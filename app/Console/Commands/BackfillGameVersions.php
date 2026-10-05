<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Report;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:backfill-game-versions {--game-version= : Assign this game version ID to phases and guild ranks missing one}')]
#[Description('Assign a game version to dataset rows missing one, then resolve the stored game version of every report and event.')]
class BackfillGameVersions extends Command
{
    /**
     * The game version to assign to dataset rows missing one, or null to skip the dataset backfill.
     */
    private ?GameVersion $gameVersion = null;

    public function handle(): int
    {
        if (! $this->resolveGameVersion()) {
            return self::FAILURE;
        }

        $this->backfillDatasets();
        $this->backfillReports();
        $this->backfillEvents();

        return self::SUCCESS;
    }

    /**
     * Resolve the game version from the --game-version option, falling back to
     * the sole game version when the option is omitted. Returns false when the
     * option is invalid or names a game version that does not exist.
     */
    private function resolveGameVersion(): bool
    {
        $gameVersionOption = $this->option('game-version');

        if ($gameVersionOption === null) {
            $this->gameVersion = $this->soleGameVersion();

            return true;
        }

        if (! ctype_digit((string) $gameVersionOption)) {
            $this->error('The --game-version option must be a numeric game version ID.');

            return false;
        }

        $this->gameVersion = GameVersion::find((int) $gameVersionOption);

        if ($this->gameVersion === null) {
            $this->error("Game version {$gameVersionOption} not found.");

            return false;
        }

        return true;
    }

    /**
     * The only game version, or null when there are none or several.
     */
    private function soleGameVersion(): ?GameVersion
    {
        $gameVersions = GameVersion::limit(2)->get();

        return $gameVersions->containsOneItem() ? $gameVersions->first() : null;
    }

    /**
     * Assign the game version to every phase and guild rank missing one, and
     * attach every playable race and class to it.
     */
    private function backfillDatasets(): void
    {
        if ($this->gameVersion === null) {
            $this->warn('Skipping dataset backfill: pass --game-version when there is not exactly one game version.');

            return;
        }

        foreach ([Phase::class, GuildRank::class] as $model) {
            $updated = $model::whereNull('game_version_id')
                ->update(['game_version_id' => $this->gameVersion->id]);

            $this->line("{$model}: {$updated} rows backfilled.");
        }

        $this->gameVersion->playableRaces()->syncWithoutDetaching(PlayableRace::pluck('id'));
        $this->gameVersion->playableClasses()->syncWithoutDetaching(PlayableClass::pluck('id'));
    }

    /**
     * Re-save every report so its saving hook re-derives its game version.
     */
    private function backfillReports(): void
    {
        $updated = 0;

        foreach (Report::lazyById() as $report) {
            $report->save();

            if ($report->wasChanged('game_version_id')) {
                $updated++;
            }
        }

        $this->info("Updated {$updated} report(s).");
    }

    /**
     * Refresh every event's game version from the phases of its raids.
     */
    private function backfillEvents(): void
    {
        $updated = 0;

        foreach (Event::lazyById() as $event) {
            $event->refreshGameVersion();

            if ($event->wasChanged('game_version_id')) {
                $updated++;
            }
        }

        $this->info("Updated {$updated} event(s).");
    }
}
