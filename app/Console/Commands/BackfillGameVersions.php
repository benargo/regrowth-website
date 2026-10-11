<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Report;
use App\Models\WarcraftLogs\GuildTag;
use App\Observers\ReportObserver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:backfill-game-versions {--game-version= : Assign this game version ID, and its Warcraft Logs guild, to phases, guild ranks, characters, guild tags and reports missing one}')]
#[Description('Assign a game version and Warcraft Logs guild to rows missing one, then resolve the stored game version of every report and event.')]
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
        $this->backfillWarcraftLogsGuilds();
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
     * Assign the game version to every phase, guild rank and character missing
     * one, and attach every playable race and class to it.
     */
    private function backfillDatasets(): void
    {
        if ($this->gameVersion === null) {
            $this->warn('Skipping dataset backfill: pass --game-version when there is not exactly one game version.');

            return;
        }

        foreach ([Phase::class, GuildRank::class, Character::class] as $model) {
            $updated = $model::whereNull('game_version_id')
                ->update(['game_version_id' => $this->gameVersion->id]);

            $this->line("{$model}: {$updated} rows backfilled.");
        }

        $this->gameVersion->playableRaces()->syncWithoutDetaching(PlayableRace::pluck('id'));
        $this->gameVersion->playableClasses()->syncWithoutDetaching(PlayableClass::pluck('id'));
    }

    /**
     * Give guild tags and reports missing a Warcraft Logs guild one.
     */
    private function backfillWarcraftLogsGuilds(): void
    {
        $guildId = $this->gameVersion?->warcraft_logs_guild_id;

        if ($guildId === null) {
            $this->warn('Skipping Warcraft Logs guild backfill: pass --game-version for a game version with a Warcraft Logs guild.');

            return;
        }

        $tags = GuildTag::whereNull('warcraft_logs_guild_id')->update(['warcraft_logs_guild_id' => $guildId]);

        $this->line("Guild tags: {$tags} rows given a Warcraft Logs guild.");

        $reports = 0;

        foreach (GuildTag::whereNotNull('warcraft_logs_guild_id')->get() as $guildTag) {
            $reports += Report::whereNull('warcraft_logs_guild_id')
                ->whereBelongsTo($guildTag)
                ->update(['warcraft_logs_guild_id' => $guildTag->warcraft_logs_guild_id]);
        }

        $reports += Report::whereNull('warcraft_logs_guild_id')->update(['warcraft_logs_guild_id' => $guildId]);

        $this->line("Reports: {$reports} rows given a Warcraft Logs guild.");
    }

    /**
     * Re-save every report so its saving hook re-derives its game version and phase.
     */
    private function backfillReports(): void
    {
        $updated = 0;

        ReportObserver::deferFlushing(function () use (&$updated): void {
            foreach (Report::lazyById() as $report) {
                $report->save();

                if ($report->wasChanged(['game_version_id', 'phase_id'])) {
                    $updated++;
                }
            }
        });

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
