<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Character;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Report;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
#[Group('raiding')]
class BackfillGameVersionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_assigns_the_given_game_version_to_datasets_missing_one_and_attaches_races_and_classes(): void
    {
        $gameVersion = GameVersion::factory()->create();
        GameVersion::factory()->create();
        $phase = Phase::factory()->create(['game_version_id' => null]);
        $guildRank = GuildRank::factory()->create(['game_version_id' => null]);
        $race = PlayableRace::factory()->create();
        $class = PlayableClass::factory()->create();

        $this->artisan('app:backfill-game-versions', ['--game-version' => $gameVersion->id])
            ->expectsOutputToContain(Phase::class.': 1 rows backfilled.')
            ->assertSuccessful();

        $this->assertSame($gameVersion->id, $phase->fresh()->game_version_id);
        $this->assertSame($gameVersion->id, $guildRank->fresh()->game_version_id);
        $this->assertDatabaseHas('pivot_game_versions_playable_races', ['game_version_id' => $gameVersion->id, 'playable_race_id' => $race->id]);
        $this->assertDatabaseHas('pivot_game_versions_playable_classes', ['game_version_id' => $gameVersion->id, 'playable_class_id' => $class->id]);
    }

    #[Test]
    public function it_uses_the_sole_game_version_when_none_is_given(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->create(['game_version_id' => null]);

        $this->artisan('app:backfill-game-versions')->assertSuccessful();

        $this->assertSame($gameVersion->id, $phase->fresh()->game_version_id);
    }

    #[Test]
    public function it_does_not_reassign_datasets_that_already_have_a_game_version(): void
    {
        $targetVersion = GameVersion::factory()->create();
        $otherVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->for($otherVersion)->create();

        $this->artisan('app:backfill-game-versions', ['--game-version' => $targetVersion->id])->assertSuccessful();

        $this->assertSame($otherVersion->id, $phase->fresh()->game_version_id);
    }

    #[Test]
    public function it_skips_the_dataset_step_but_still_resolves_reports_when_several_game_versions_exist_and_none_is_given(): void
    {
        $gameVersion = GameVersion::factory()->forGuild()->create();
        GameVersion::factory()->create();
        $phase = Phase::factory()->create(['game_version_id' => null]);
        $report = Report::factory()->forGameVersion($gameVersion)->create();
        Report::whereKey($report->id)->update(['game_version_id' => null]);

        $this->artisan('app:backfill-game-versions')
            ->expectsOutputToContain('Skipping dataset backfill')
            ->expectsOutputToContain('Updated 1 report(s).')
            ->assertSuccessful();

        $this->assertNull($phase->fresh()->game_version_id);
        $this->assertSame($gameVersion->id, $report->fresh()->game_version_id);
    }

    #[Test]
    #[Group('validation')]
    public function it_fails_without_changes_when_the_given_game_version_does_not_exist(): void
    {
        $phase = Phase::factory()->create(['game_version_id' => null]);

        $this->artisan('app:backfill-game-versions', ['--game-version' => 999999])
            ->expectsOutputToContain('Game version 999999 not found.')
            ->assertFailed();

        $this->assertNull($phase->fresh()->game_version_id);
    }

    #[Test]
    #[Group('validation')]
    public function it_fails_when_the_given_game_version_is_not_numeric(): void
    {
        $this->artisan('app:backfill-game-versions', ['--game-version' => 'tbc'])
            ->expectsOutputToContain('The --game-version option must be a numeric game version ID.')
            ->assertFailed();
    }

    #[Test]
    #[Group('happy-path')]
    public function it_fills_the_game_version_of_existing_reports_and_events(): void
    {
        $gameVersion = GameVersion::factory()->forGuild()->create();
        GameVersion::factory()->create();
        $report = Report::factory()->forGameVersion($gameVersion)->create();
        $event = Event::factory()->forGameVersion($gameVersion)->create();
        Report::whereKey($report->id)->update(['game_version_id' => null]);
        Event::whereKey($event->id)->update(['game_version_id' => null]);

        $this->artisan('app:backfill-game-versions')
            ->expectsOutputToContain('Updated 1 report(s).')
            ->expectsOutputToContain('Updated 1 event(s).')
            ->assertSuccessful();

        $this->assertSame($gameVersion->id, $report->fresh()->game_version_id);
        $this->assertSame($gameVersion->id, $event->fresh()->game_version_id);
    }

    #[Test]
    public function it_changes_nothing_on_a_second_run(): void
    {
        $gameVersion = GameVersion::factory()->forGuild()->create();
        Phase::factory()->create(['game_version_id' => null]);
        PlayableRace::factory()->create();
        Report::factory()->forGameVersion($gameVersion)->create();
        Event::factory()->forGameVersion($gameVersion)->create();
        $this->artisan('app:backfill-game-versions')->assertSuccessful();

        $this->artisan('app:backfill-game-versions')
            ->expectsOutputToContain(Phase::class.': 0 rows backfilled.')
            ->expectsOutputToContain('Updated 0 report(s).')
            ->expectsOutputToContain('Updated 0 event(s).')
            ->assertSuccessful();

        $this->assertSame(1, $gameVersion->playableRaces()->count());
    }

    // ==================== warcraft logs guilds ====================

    #[Test]
    public function it_gives_guild_tags_missing_a_guild_the_game_versions_guild(): void
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create();
        $guildTag = GuildTag::factory()->create();

        $this->artisan('app:backfill-game-versions', ['--game-version' => $gameVersion->id])
            ->expectsOutputToContain('Guild tags: 1 rows given a Warcraft Logs guild.')
            ->assertSuccessful();

        $this->assertSame($guild->id, $guildTag->fresh()->warcraft_logs_guild_id);
    }

    #[Test]
    public function it_gives_a_report_its_tags_guild_before_the_game_versions(): void
    {
        $gameVersion = GameVersion::factory()->forGuild()->create();
        $tagsGuild = Guild::factory()->create();
        $report = Report::factory()->withGuildTag(GuildTag::factory()->forGuild($tagsGuild)->create())->create();

        $this->artisan('app:backfill-game-versions', ['--game-version' => $gameVersion->id])
            ->expectsOutputToContain('Reports: 1 rows given a Warcraft Logs guild.')
            ->assertSuccessful();

        $this->assertSame($tagsGuild->id, $report->fresh()->warcraft_logs_guild_id);
    }

    #[Test]
    public function it_gives_a_report_without_a_tag_the_game_versions_guild(): void
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create();
        $report = Report::factory()->withoutGuildTag()->create();

        $this->artisan('app:backfill-game-versions', ['--game-version' => $gameVersion->id])->assertSuccessful();

        $this->assertSame($guild->id, $report->fresh()->warcraft_logs_guild_id);
    }

    #[Test]
    public function it_never_reassigns_a_tag_or_report_that_already_has_a_guild(): void
    {
        $gameVersion = GameVersion::factory()->forGuild()->create();
        $otherGuild = Guild::factory()->create();
        $guildTag = GuildTag::factory()->forGuild($otherGuild)->create();
        $report = Report::factory()->forGuild($otherGuild)->withoutGuildTag()->create();

        $this->artisan('app:backfill-game-versions', ['--game-version' => $gameVersion->id])
            ->expectsOutputToContain('Guild tags: 0 rows given a Warcraft Logs guild.')
            ->expectsOutputToContain('Reports: 0 rows given a Warcraft Logs guild.')
            ->assertSuccessful();

        $this->assertSame($otherGuild->id, $guildTag->fresh()->warcraft_logs_guild_id);
        $this->assertSame($otherGuild->id, $report->fresh()->warcraft_logs_guild_id);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_derives_report_versions_after_filling_their_guilds(): void
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);
        $report = Report::factory()->withoutGuildTag()->create(['start_time' => '2025-03-14 19:30:00']);

        $this->artisan('app:backfill-game-versions', ['--game-version' => $gameVersion->id])
            ->expectsOutputToContain('Updated 1 report(s).')
            ->assertSuccessful();

        $fresh = $report->fresh();
        $this->assertSame($guild->id, $fresh->warcraft_logs_guild_id);
        $this->assertSame($gameVersion->id, $fresh->game_version_id);
    }

    #[Test]
    public function it_skips_the_guild_backfill_when_the_game_version_has_no_guild(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $guildTag = GuildTag::factory()->create();

        $this->artisan('app:backfill-game-versions', ['--game-version' => $gameVersion->id])
            ->expectsOutputToContain('Skipping Warcraft Logs guild backfill: pass --game-version for a game version with a Warcraft Logs guild.')
            ->assertSuccessful();

        $this->assertNull($guildTag->fresh()->warcraft_logs_guild_id);
    }

    #[Test]
    public function it_keeps_every_attendance_row_and_report_link(): void
    {
        $gameVersion = GameVersion::factory()->forGuild()->create();
        $report = Report::factory()->withoutGuildTag()->create();
        $linkedReport = Report::factory()->withoutGuildTag()->create();
        $character = Character::factory()->create();
        $report->characters()->attach($character->id, ['presence' => 1]);
        $report->linkedReports()->attach($linkedReport->id);

        $this->artisan('app:backfill-game-versions', ['--game-version' => $gameVersion->id])->assertSuccessful();

        $this->assertDatabaseHas('pivot_characters_raid_reports', ['character_id' => $character->id, 'raid_report_id' => $report->id, 'presence' => 1]);
        $this->assertDatabaseHas('pivot_report_links', ['report_1' => $report->id, 'report_2' => $linkedReport->id]);
    }
}
