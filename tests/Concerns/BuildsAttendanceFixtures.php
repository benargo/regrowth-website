<?php

namespace Tests\Concerns;

use App\Enums\Presence;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Report;
use App\Models\WarcraftLogs\GuildTag;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Builds attendance fixtures inside one game version: a version with a
 * Warcraft Logs guild released on 2024-01-01, tags and ranks of that
 * version, and reports whose guild and start time derive the version.
 */
trait BuildsAttendanceFixtures
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeAttendanceVersion(array $attributes = []): GameVersion
    {
        return GameVersion::factory()->forGuild()->create([
            'release_date' => '2024-01-01 00:00:00',
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeTag(GameVersion $gameVersion, bool $countsAttendance = true, array $attributes = []): GuildTag
    {
        return GuildTag::factory()
            ->forGuild($gameVersion->warcraftLogsGuild)
            ->create(['count_attendance' => $countsAttendance, ...$attributes]);
    }

    protected function makeRank(GameVersion $gameVersion, bool $countsAttendance = true): GuildRank
    {
        return GuildRank::factory()->for($gameVersion)->create(['count_attendance' => $countsAttendance]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeCharacter(GameVersion $gameVersion, ?GuildRank $rank = null, array $attributes = []): Character
    {
        return Character::factory()
            ->forGameVersion($gameVersion)
            ->withUniqueName()
            ->create(['rank_id' => $rank?->id, ...$attributes]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function makeReport(GameVersion $gameVersion, CarbonInterface|string $startTime, ?GuildTag $tag = null, array $attributes = []): Report
    {
        return Report::factory()
            ->forGuild($gameVersion->warcraftLogsGuild)
            ->withGuildTag($tag ?? $this->makeTag($gameVersion))
            ->create(['start_time' => $startTime, ...$attributes]);
    }

    protected function attend(Report $report, Character $character, Presence $presence = Presence::Present): void
    {
        $report->characters()->attach($character->id, ['presence' => $presence->value]);
    }

    protected function linkReports(Report $first, Report $second): void
    {
        $first->linkedReports()->attach($second->id);
        $second->linkedReports()->attach($first->id);
    }

    protected function linkCharacters(Character $main, Character $alt): void
    {
        DB::table('character_links')->insert([
            ['character_id' => $main->id, 'linked_character_id' => $alt->id, 'created_at' => now(), 'updated_at' => now()],
            ['character_id' => $alt->id, 'linked_character_id' => $main->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
