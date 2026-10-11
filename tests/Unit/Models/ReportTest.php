<?php

namespace Tests\Unit\Models;

use App\Events\AddonSettingsProcessed;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Report;
use App\Models\User;
use App\Models\WarcraftLogs\Guild;
use App\Models\WarcraftLogs\GuildTag;
use App\Models\WarcraftLogs\Zone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('raiding')]
#[Group('warcraftlogs-integration')]
class ReportTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Report::class;
    }

    #[Test]
    public function it_uses_wcl_reports_table(): void
    {
        $report = new Report;

        $this->assertSame('reports', $report->getTable());
    }

    #[Test]
    public function it_has_string_primary_key(): void
    {
        $report = new Report;

        $this->assertSame('id', $report->getKeyName());
        $this->assertFalse($report->getIncrementing());
        $this->assertSame('string', $report->getKeyType());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $report = new Report;

        $this->assertFillable($report, [
            'code',
            'title',
            'start_time',
            'end_time',
            'guild_tag_id',
            'zone_id',
            'warcraft_logs_guild_id',
        ]);
    }

    #[Test]
    public function it_declares_fillable_via_attribute(): void
    {
        $report = new Report;

        $this->assertFillableAttribute($report, [
            'code',
            'title',
            'start_time',
            'end_time',
            'guild_tag_id',
            'zone_id',
            'warcraft_logs_guild_id',
        ]);
    }

    #[Test]
    public function it_has_expected_casts(): void
    {
        $report = new Report;

        $this->assertCasts($report, [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ]);
    }

    #[Test]
    public function it_hides_correct_attributes(): void
    {
        $report = new Report;

        $this->assertHidden($report, [
            'created_at',
            'updated_at',
            'zone_id',
        ]);
    }

    #[Test]
    public function it_can_be_created_with_factory(): void
    {
        $report = $this->create();

        $this->assertTableHas([
            'code' => $report->code,
        ]);
    }

    #[Test]
    public function it_casts_dates_correctly(): void
    {
        $report = $this->create([
            'start_time' => '2026-01-15 20:00:00',
            'end_time' => '2026-01-15 23:30:00',
        ]);

        $report->refresh();

        $this->assertInstanceOf(Carbon::class, $report->start_time);
        $this->assertInstanceOf(Carbon::class, $report->end_time);
    }

    #[Test]
    public function duration_returns_seconds_between_start_and_end_time(): void
    {
        $report = $this->create([
            'start_time' => '2026-01-15 20:00:00',
            'end_time' => '2026-01-15 23:30:00',
        ]);

        $report->refresh();

        $this->assertSame(12600.0, $report->duration);
    }

    #[Test]
    public function duration_returns_zero_when_start_and_end_time_are_equal(): void
    {
        $report = $this->create([
            'start_time' => '2026-01-15 20:00:00',
            'end_time' => '2026-01-15 20:00:00',
        ]);

        $report->refresh();

        $this->assertSame(0.0, $report->duration);
    }

    #[Test]
    public function it_can_be_created_without_zone(): void
    {
        $report = $this->factory()->withoutZone()->create();

        $this->assertTableHas([
            'code' => $report->code,
            'zone_id' => null,
        ]);
    }

    #[Test]
    public function zone_returns_belongs_to_relationship(): void
    {
        $report = new Report;

        $this->assertInstanceOf(BelongsTo::class, $report->zone());
    }

    #[Test]
    public function zone_relationship_returns_zone_model(): void
    {
        $zone = Zone::factory()->create();
        $report = $this->factory()->withZone($zone)->create();

        $report->refresh();

        $this->assertInstanceOf(Zone::class, $report->zone);
        $this->assertSame($zone->id, $report->zone->id);
        $this->assertSame($zone->name, $report->zone->name);
    }

    #[Test]
    public function zone_relationship_returns_default_when_no_zone(): void
    {
        $report = $this->factory()->withoutZone()->create();

        $report->refresh();

        $this->assertNotNull($report->zone);
        $this->assertSame(0, $report->zone->id);
        $this->assertSame('No zone', $report->zone->name);
        $this->assertCount(0, $report->zone->difficulties);
    }

    #[Test]
    public function zone_default_returns_model_instance(): void
    {
        $report = $this->factory()->withoutZone()->create();

        $report->refresh();

        $this->assertInstanceOf(Zone::class, $report->zone);
    }

    #[Test]
    public function expansion_accessor_returns_expansion_from_zone(): void
    {
        $zone = Zone::factory()->create();
        $report = $this->factory()->withZone($zone)->create();

        $report->refresh();
        $report->load('zone');

        $this->assertSame($zone->expansion->id, $report->expansion->id);
        $this->assertSame($zone->expansion->name, $report->expansion->name);
    }

    #[Test]
    public function expansion_accessor_returns_null_when_zone_is_default(): void
    {
        $report = $this->factory()->withoutZone()->create();

        $report->refresh();

        $this->assertNull($report->expansion);
    }

    #[Test]
    public function characters_returns_belongs_to_many_relationship(): void
    {
        $report = new Report;

        $this->assertInstanceOf(BelongsToMany::class, $report->characters());
    }

    #[Test]
    public function it_can_attach_characters(): void
    {
        $report = $this->create();
        $character = Character::factory()->create();

        $report->characters()->attach($character->id);

        $this->assertCount(1, $report->characters);
        $this->assertSame($character->id, $report->characters->first()->id);
    }

    #[Test]
    public function it_can_attach_multiple_characters(): void
    {
        $report = $this->create();
        $characters = Character::factory()->count(3)->create();

        $report->characters()->attach($characters->pluck('id'));

        $this->assertCount(3, $report->characters);
    }

    #[Test]
    public function characters_returns_empty_collection_when_none_attached(): void
    {
        $report = $this->create();

        $this->assertCount(0, $report->characters);
    }

    #[Test]
    public function presence_defaults_to_zero(): void
    {
        $report = $this->create();
        $character = Character::factory()->create();

        $report->characters()->attach($character->id);

        $this->assertSame(0, $report->characters->first()->pivot->presence);
    }

    #[Test]
    public function presence_can_be_set_when_attaching(): void
    {
        $report = $this->create();
        $character = Character::factory()->create();

        $report->characters()->attach($character->id, ['presence' => 1]);

        $this->assertSame(1, $report->characters->first()->pivot->presence);
    }

    #[Test]
    public function presence_can_be_set_to_benched(): void
    {
        $report = $this->create();
        $character = Character::factory()->create();

        $report->characters()->attach($character->id, ['presence' => 2]);

        $this->assertSame(2, $report->characters->first()->pivot->presence);
    }

    #[Test]
    public function deleting_report_cascades_to_pivot(): void
    {
        $report = $this->create();
        $character = Character::factory()->create();

        $report->characters()->attach($character->id);

        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'raid_report_id' => $report->id,
            'character_id' => $character->id,
        ]);

        $report->delete();

        $this->assertDatabaseMissing('pivot_characters_raid_reports', [
            'raid_report_id' => $report->id,
        ]);
    }

    #[Test]
    public function deleting_character_cascades_to_pivot(): void
    {
        Event::fake([AddonSettingsProcessed::class]);

        $report = $this->create();
        $character = Character::factory()->create();

        $report->characters()->attach($character->id);

        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'raid_report_id' => $report->id,
            'character_id' => $character->id,
        ]);

        $character->delete();

        $this->assertDatabaseMissing('pivot_characters_raid_reports', [
            'character_id' => $character->id,
        ]);
    }

    #[Test]
    public function it_belongs_to_a_guild_tag(): void
    {
        $guildTag = GuildTag::factory()->create();
        $report = $this->factory()->withGuildTag($guildTag)->create();

        $this->assertRelation($report, 'guildTag', BelongsTo::class);
        $this->assertSame($guildTag->id, $report->guildTag->id);
    }

    #[Test]
    public function guild_tag_relationship_returns_null_when_no_guild_tag_associated(): void
    {
        $report = $this->factory()->withoutGuildTag()->create();

        $this->assertNull($report->guildTag);
    }

    #[Test]
    public function factory_with_guild_tag_state_associates_a_guild_tag(): void
    {
        $report = $this->factory()->withGuildTag()->create();

        $this->assertNotNull($report->guild_tag_id);
        $this->assertNotNull($report->guildTag);
    }

    #[Test]
    public function factory_with_guild_tag_state_accepts_specific_guild_tag(): void
    {
        $guildTag = GuildTag::factory()->create(['name' => 'Main Roster']);

        $report = $this->factory()->withGuildTag($guildTag)->create();

        $this->assertSame($guildTag->id, $report->guild_tag_id);
        $this->assertSame('Main Roster', $report->guildTag->name);
    }

    #[Test]
    public function factory_without_guild_tag_state_sets_null_guild_tag(): void
    {
        $report = $this->factory()->withoutGuildTag()->create();

        $this->assertNull($report->guild_tag_id);
    }

    #[Test]
    public function deleting_guild_tag_sets_report_guild_tag_id_to_null(): void
    {
        $guildTag = GuildTag::factory()->create();
        $report = $this->factory()->withGuildTag($guildTag)->create();

        $this->assertSame($guildTag->id, $report->guild_tag_id);

        $guildTag->delete();

        $report->refresh();

        $this->assertNull($report->guild_tag_id);
    }

    // ==================== warcraftLogsGuild and phase ====================

    #[Test]
    public function it_belongs_to_a_warcraft_logs_guild(): void
    {
        $guild = Guild::factory()->create();
        $report = $this->factory()->forGuild($guild)->create();

        $this->assertRelation($report, 'warcraftLogsGuild', BelongsTo::class);
        $this->assertTrue($report->warcraftLogsGuild->is($guild));
    }

    #[Test]
    public function it_belongs_to_a_phase(): void
    {
        $phase = Phase::factory()->create();
        $report = $this->factory()->create();
        Report::whereKey($report->id)->update(['phase_id' => $phase->id]);

        $report->refresh();

        $this->assertRelation($report, 'phase', BelongsTo::class);
        $this->assertTrue($report->phase->is($phase));
    }

    #[Test]
    public function deleting_its_phase_clears_the_reports_phase(): void
    {
        $phase = Phase::factory()->create();
        $report = $this->factory()->create();
        Report::whereKey($report->id)->update(['phase_id' => $phase->id]);

        $phase->delete();

        $this->assertNull($report->fresh()->phase_id);
    }

    // ==================== derived game version and phase ====================

    #[Test]
    public function it_takes_the_latest_version_of_its_guild_released_by_its_start_time(): void
    {
        $guild = Guild::factory()->create();
        GameVersion::factory()->forGuild($guild)->create(['release_date' => '2021-09-04 00:00:00']);
        $latest = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);

        $report = $this->factory()->forGuild($guild)->create(['start_time' => '2025-03-14 19:30:00']);

        $this->assertRelation($report, 'gameVersion', BelongsTo::class);
        $this->assertTrue($report->gameVersion->is($latest));
    }

    #[Test]
    #[Group('edge-case')]
    public function it_takes_a_version_released_at_the_reports_start_time(): void
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);

        $report = $this->factory()->forGuild($guild)->create(['start_time' => '2024-11-22 00:00:00']);

        $this->assertSame($gameVersion->id, $report->game_version_id);
    }

    #[Test]
    public function it_ignores_versions_released_after_its_start_time(): void
    {
        $guild = Guild::factory()->create();
        $released = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);
        GameVersion::factory()->forGuild($guild)->create(['release_date' => '2026-02-06 00:00:00']);

        $report = $this->factory()->forGuild($guild)->create(['start_time' => '2025-03-14 19:30:00']);

        $this->assertSame($released->id, $report->game_version_id);
    }

    #[Test]
    public function it_ignores_another_guilds_versions(): void
    {
        $guild = Guild::factory()->create();
        $own = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);
        GameVersion::factory()->forGuild()->create(['release_date' => '2025-01-01 00:00:00']);

        $report = $this->factory()->forGuild($guild)->create(['start_time' => '2025-03-14 19:30:00']);

        $this->assertSame($own->id, $report->game_version_id);
    }

    #[Test]
    public function it_has_no_version_or_phase_when_it_predates_every_version_of_its_guild(): void
    {
        $guild = Guild::factory()->create();
        GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);

        $report = $this->factory()->forGuild($guild)->create(['start_time' => '2024-10-01 19:30:00']);

        $this->assertNull($report->game_version_id);
        $this->assertNull($report->phase_id);
    }

    #[Test]
    public function it_has_no_version_or_phase_without_a_guild(): void
    {
        GameVersion::factory()->forGuild()->create(['release_date' => '2024-11-22 00:00:00']);

        $report = $this->factory()->create(['start_time' => '2025-03-14 19:30:00']);

        $this->assertNull($report->game_version_id);
        $this->assertNull($report->phase_id);
    }

    #[Test]
    public function it_takes_the_latest_started_phase_of_its_version(): void
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);
        Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2025-06-01 00:00:00']);
        $latest = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-01-01 00:00:00']);
        Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-06-01 00:00:00']);

        $report = $this->factory()->forGuild($guild)->create(['start_time' => '2026-02-12 19:30:00']);

        $this->assertTrue($report->phase->is($latest));
    }

    #[Test]
    public function it_ignores_phases_without_a_start_date(): void
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);
        $started = Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-01-01 00:00:00']);
        Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => null]);

        $report = $this->factory()->forGuild($guild)->create(['start_time' => '2026-02-12 19:30:00']);

        $this->assertSame($started->id, $report->phase_id);
    }

    #[Test]
    public function it_ignores_phases_of_another_version_of_its_guild(): void
    {
        $guild = Guild::factory()->create();
        $olderVersion = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2021-09-04 00:00:00']);
        $gameVersion = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);
        Phase::factory()->forGameVersion($olderVersion)->create(['start_date' => '2025-01-01 00:00:00']);

        $report = $this->factory()->forGuild($guild)->create(['start_time' => '2025-03-14 19:30:00']);

        $this->assertSame($gameVersion->id, $report->game_version_id);
        $this->assertNull($report->phase_id);
    }

    #[Test]
    public function it_has_a_version_but_no_phase_when_it_predates_every_phase(): void
    {
        $guild = Guild::factory()->create();
        $gameVersion = GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);
        Phase::factory()->forGameVersion($gameVersion)->create(['start_date' => '2026-01-01 00:00:00']);

        $report = $this->factory()->forGuild($guild)->create(['start_time' => '2025-03-14 19:30:00']);

        $this->assertSame($gameVersion->id, $report->game_version_id);
        $this->assertNull($report->phase_id);
    }

    #[Test]
    public function saving_rederives_after_its_guild_changes(): void
    {
        $oldVersion = GameVersion::factory()->forGuild()->create(['release_date' => '2024-11-22 00:00:00']);
        $newVersion = GameVersion::factory()->forGuild()->create(['release_date' => '2024-11-22 00:00:00']);
        $report = $this->factory()->forGuild($oldVersion->warcraftLogsGuild)->create(['start_time' => '2025-03-14 19:30:00']);

        $report->update(['warcraft_logs_guild_id' => $newVersion->warcraft_logs_guild_id]);

        $this->assertSame($newVersion->id, $report->fresh()->game_version_id);
    }

    #[Test]
    public function saving_overwrites_a_directly_assigned_game_version_and_phase(): void
    {
        $report = $this->factory()->create();
        $report->game_version_id = GameVersion::factory()->create()->id;
        $report->phase_id = Phase::factory()->create()->id;

        $report->save();

        $fresh = $report->fresh();
        $this->assertNull($fresh->game_version_id);
        $this->assertNull($fresh->phase_id);
    }

    #[Test]
    public function saving_keeps_its_guild_tag_and_attendance_rows(): void
    {
        $guild = Guild::factory()->create();
        GameVersion::factory()->forGuild($guild)->create(['release_date' => '2024-11-22 00:00:00']);
        $guildTag = GuildTag::factory()->forGuild($guild)->create();
        $report = $this->factory()->forGuild($guild)->withGuildTag($guildTag)->create(['start_time' => '2025-03-14 19:30:00']);
        $character = Character::factory()->create();
        $report->characters()->attach($character->id, ['presence' => 1]);

        $report->save();

        $this->assertSame($guildTag->id, $report->fresh()->guild_tag_id);
        $this->assertDatabaseHas('pivot_characters_raid_reports', [
            'character_id' => $character->id,
            'raid_report_id' => $report->id,
            'presence' => 1,
        ]);
    }

    #[Test]
    public function factory_for_game_version_state_gives_the_report_that_version_through_its_guild(): void
    {
        $gameVersion = GameVersion::factory()->forGuild()->create();

        $report = $this->factory()->forGameVersion($gameVersion)->create();

        $this->assertSame($gameVersion->id, $report->game_version_id);
        $this->assertSame($gameVersion->warcraft_logs_guild_id, $report->warcraft_logs_guild_id);
        $this->assertSame($gameVersion->warcraft_logs_guild_id, $report->guildTag->warcraft_logs_guild_id);
    }

    #[Test]
    #[Group('error-handling')]
    public function factory_for_game_version_state_needs_a_version_with_a_guild(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('forGameVersion() needs a game version with a Warcraft Logs guild.');

        $this->factory()->forGameVersion(GameVersion::factory()->create());
    }

    // ==================== linkedReports ====================

    #[Test]
    public function linked_reports_returns_belongs_to_many_relationship(): void
    {
        $report = new Report;

        $this->assertInstanceOf(BelongsToMany::class, $report->linkedReports());
    }

    #[Test]
    public function linked_reports_returns_linked_reports(): void
    {
        $report1 = $this->create();
        $report2 = $this->create();

        DB::table('pivot_report_links')->insert([
            ['report_1' => $report1->id, 'report_2' => $report2->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $report2->id, 'report_2' => $report1->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $linked = $report1->linkedReports;

        $this->assertCount(1, $linked);
        $this->assertSame($report2->code, $linked->first()->code);
    }

    #[Test]
    public function linked_reports_returns_manually_linked_reports_with_created_by(): void
    {
        $report1 = $this->create();
        $report2 = $this->create();
        $officer = User::factory()->officer()->create();

        DB::table('pivot_report_links')->insert([
            ['report_1' => $report1->id, 'report_2' => $report2->id, 'created_by' => $officer->id, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $report2->id, 'report_2' => $report1->id, 'created_by' => $officer->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $linked = $report1->linkedReports;

        $this->assertCount(1, $linked);
        $this->assertSame($report2->code, $linked->first()->code);
        $this->assertSame($officer->id, $linked->first()->pivot->created_by);
    }

    #[Test]
    public function deleting_report_cascades_to_linked_reports_pivot(): void
    {
        $report1 = $this->create();
        $report2 = $this->create();

        DB::table('pivot_report_links')->insert([
            ['report_1' => $report1->id, 'report_2' => $report2->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
            ['report_1' => $report2->id, 'report_2' => $report1->id, 'created_by' => null, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $report1->delete();

        $this->assertDatabaseMissing('pivot_report_links', ['report_1' => $report1->id]);
        $this->assertDatabaseMissing('pivot_report_links', ['report_2' => $report1->id]);
    }
}
