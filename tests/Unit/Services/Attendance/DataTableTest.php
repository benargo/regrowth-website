<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\Raids\Report;
use App\Models\GuildTag;
use App\Services\Attendance\Calculator;
use App\Services\Attendance\CharacterAttendanceRowData;
use App\Services\Attendance\DataTable;
use App\Services\Attendance\FiltersData;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DataTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Europe/Paris']);
    }

    protected function makeDataTable(FiltersData $filters = new FiltersData): DataTable
    {
        return new DataTable(new Calculator, $filters);
    }

    protected function makeRank(bool $countsAttendance = true): GuildRank
    {
        return $countsAttendance
            ? GuildRank::factory()->create()
            : GuildRank::factory()->doesNotCountAttendance()->create();
    }

    protected function makeTag(bool $countsAttendance = true): GuildTag
    {
        return $countsAttendance
            ? GuildTag::factory()->countsAttendance()->withoutPhase()->create()
            : GuildTag::factory()->doesNotCountAttendance()->withoutPhase()->create();
    }

    protected function makeReport(GuildTag $tag, Carbon $startTime): Report
    {
        return Report::factory()->withGuildTag($tag)->create(['start_time' => $startTime]);
    }

    protected function attachCharacter(Report $report, Character $character, int $presence): void
    {
        $report->characters()->attach($character->id, ['presence' => $presence]);
    }

    protected function linkCharacters(Character $main, Character $alt): void
    {
        \DB::table('character_links')->insert([
            ['character_id' => $main->id, 'linked_character_id' => $alt->id, 'created_at' => now(), 'updated_at' => now()],
            ['character_id' => $alt->id, 'linked_character_id' => $main->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    protected function linkReports(Report $report1, Report $report2): void
    {
        $report1->linkedReports()->attach($report2->id);
        $report2->linkedReports()->attach($report1->id);
    }

    // ==================== columns() Tests ====================

    #[Test]
    public function columns_returns_empty_collection_when_no_reports_exist(): void
    {
        $table = $this->makeDataTable();

        $this->assertTrue($table->columns()->isEmpty());
    }

    #[Test]
    public function columns_returns_metadata_for_each_report(): void
    {
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));

        $columns = $this->makeDataTable()->columns();

        $this->assertCount(1, $columns);
        $this->assertEquals($report->id, $columns->first()['id']);
        $this->assertEquals('Wed', $columns->first()['dayOfWeek']);
        $this->assertEquals('01/01', $columns->first()['date']);
    }

    #[Test]
    public function columns_returns_in_reverse_chronological_order(): void
    {
        $tag = $this->makeTag();
        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));

        $columns = $this->makeDataTable()->columns();

        $this->assertCount(2, $columns);
        $this->assertEquals($report2->id, $columns->first()['id']);
        $this->assertEquals($report1->id, $columns->last()['id']);
    }

    #[Test]
    public function columns_merges_linked_reports_into_a_single_column(): void
    {
        $tag = $this->makeTag();
        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 19:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-01 21:00', 'Europe/Paris'));
        $this->linkReports($report1, $report2);

        $columns = $this->makeDataTable()->columns();

        $this->assertCount(1, $columns);
    }

    #[Test]
    public function columns_includes_zone_name_and_report_code(): void
    {
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));

        $columns = $this->makeDataTable()->columns();

        $this->assertArrayHasKey('zoneName', $columns->first());
        $this->assertArrayHasKey('code', $columns->first());
    }

    #[Test]
    public function columns_excludes_reports_from_non_counting_guild_tags(): void
    {
        $tag = $this->makeTag(false);
        $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));

        $columns = $this->makeDataTable()->columns();

        $this->assertTrue($columns->isEmpty());
    }

    #[Test]
    public function columns_filters_by_guild_tag_ids(): void
    {
        $tag1 = $this->makeTag();
        $tag2 = $this->makeTag();
        $this->makeReport($tag1, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->makeReport($tag2, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));

        $filters = new FiltersData(guildTagIds: [$tag1->id]);
        $table = $this->makeDataTable($filters);

        $this->assertCount(1, $table->columns());
    }

    #[Test]
    public function columns_filters_by_since_date(): void
    {
        $tag = $this->makeTag();
        $this->makeReport($tag, Carbon::parse('2024-12-25 20:00', 'Europe/Paris'));
        $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));

        $filters = new FiltersData(sinceDate: Carbon::parse('2025-01-01', 'Europe/Paris')->utc());
        $table = $this->makeDataTable($filters);

        $this->assertCount(1, $table->columns());
    }

    #[Test]
    public function columns_filters_by_before_date(): void
    {
        $tag = $this->makeTag();
        $this->makeReport($tag, Carbon::parse('2024-12-25 20:00', 'Europe/Paris'));
        $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));

        $filters = new FiltersData(beforeDate: Carbon::parse('2025-01-01', 'Europe/Paris')->utc());
        $table = $this->makeDataTable($filters);

        $this->assertCount(1, $table->columns());
    }

    // ==================== rows() Tests ====================

    #[Test]
    public function rows_returns_empty_collection_when_no_reports_exist(): void
    {
        $table = $this->makeDataTable();

        $this->assertTrue($table->rows()->isEmpty());
    }

    #[Test]
    public function rows_returns_alphabetically_sorted_characters(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $zebra = Character::factory()->create(['name' => 'Zebra', 'rank_id' => $rank->id]);
        $alpha = Character::factory()->create(['name' => 'Alpha', 'rank_id' => $rank->id]);
        $this->attachCharacter($report, $zebra, 1);
        $this->attachCharacter($report, $alpha, 1);

        $rows = $this->makeDataTable()->rows();

        $this->assertEquals('Alpha', $rows->first()->character->name);
        $this->assertEquals('Zebra', $rows->last()->character->name);
    }

    #[Test]
    public function rows_presence_1_counts_as_attended(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $row = $this->makeDataTable()->rows()->first();

        $this->assertEquals(100.0, $row->percentage);
        $this->assertEquals([1], $row->attendance);
    }

    #[Test]
    public function rows_presence_2_counts_as_attended(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 2);

        $row = $this->makeDataTable()->rows()->first();

        $this->assertEquals(100.0, $row->percentage);
        $this->assertEquals([2], $row->attendance);
    }

    #[Test]
    public function rows_presence_0_counts_as_absent(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 0);

        $row = $this->makeDataTable()->rows()->first();

        $this->assertEquals(0.0, $row->percentage);
        $this->assertEquals([0], $row->attendance);
    }

    #[Test]
    public function rows_missing_presence_record_counts_as_absent(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $this->attachCharacter($report1, $character, 1);
        // character not attached to report2 — should count as absent

        $row = $this->makeDataTable()->rows()->first();

        $this->assertEquals(50.0, $row->percentage);
        $this->assertContains(0, $row->attendance);
    }

    #[Test]
    public function rows_null_attendance_before_characters_first_raid(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        // character only joins on second report
        $this->attachCharacter($report2, $character, 1);

        $row = $this->makeDataTable()->rows()->first();

        // attendance is in reverse-chronological order in the records but stored forward for the row
        // the null should appear for report1 (the earlier one, which appears last in the row array)
        $this->assertContains(null, $row->attendance);
        $this->assertEquals(100.0, $row->percentage);
    }

    #[Test]
    public function rows_planned_absence_excludes_raid_from_percentage_denominator(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report1 = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $report2 = $this->makeReport($tag, Carbon::parse('2025-01-08 20:00', 'Europe/Paris'));
        $this->attachCharacter($report1, $character, 1);
        $this->attachCharacter($report2, $character, 0);

        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => '2025-01-08',
            'end_date' => '2025-01-08',
        ]);

        $row = $this->makeDataTable()->rows()->first();

        // report2 is covered by absence — only report1 counts toward percentage
        $this->assertEquals(100.0, $row->percentage);
    }

    #[Test]
    public function rows_percentage_is_zero_when_character_has_no_countable_raids(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 0);

        PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-01',
        ]);

        $row = $this->makeDataTable()->rows()->first();

        $this->assertEquals(0.0, $row->percentage);
    }

    #[Test]
    public function rows_planned_absences_array_contains_absence_for_covered_raid(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 0);

        $absence = PlannedAbsence::factory()->create([
            'character_id' => $character->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-01',
        ]);

        $row = $this->makeDataTable()->rows()->first();

        $this->assertInstanceOf(PlannedAbsence::class, $row->plannedAbsences[0]);
        $this->assertEquals($absence->id, $row->plannedAbsences[0]->id);
    }

    #[Test]
    public function rows_planned_absences_array_is_null_for_uncovered_raids(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $row = $this->makeDataTable()->rows()->first();

        $this->assertNull($row->plannedAbsences[0]);
    }

    #[Test]
    public function rows_includes_character_id_rank_id_and_playable_class(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $character = Character::factory()->create(['rank_id' => $rank->id]);
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $row = $this->makeDataTable()->rows()->first();

        $this->assertInstanceOf(CharacterAttendanceRowData::class, $row);
        $this->assertEquals($character->id, $row->character->id);
        $this->assertEquals($rank->id, $row->character->rank_id);
    }

    #[Test]
    public function rows_excludes_characters_in_non_counting_ranks_by_default(): void
    {
        $nonCountingRank = $this->makeRank(false);
        $tag = $this->makeTag();
        $character = Character::factory()->create(['rank_id' => $nonCountingRank->id]);
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);

        $rows = $this->makeDataTable()->rows();

        $this->assertTrue($rows->isEmpty());
    }

    #[Test]
    public function rows_with_rank_filter_only_includes_specified_ranks(): void
    {
        $rank1 = $this->makeRank();
        $rank2 = $this->makeRank();
        $tag = $this->makeTag();
        $char1 = Character::factory()->create(['name' => 'Char1', 'rank_id' => $rank1->id]);
        $char2 = Character::factory()->create(['name' => 'Char2', 'rank_id' => $rank2->id]);
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $char1, 1);
        $this->attachCharacter($report, $char2, 1);

        $filters = new FiltersData(rankIds: [$rank1->id]);
        $rows = $this->makeDataTable($filters)->rows();

        $this->assertCount(1, $rows);
        $this->assertEquals('Char1', $rows->first()->character->name);
    }

    #[Test]
    public function rows_with_linked_characters_flag_includes_alts_without_rank_filter(): void
    {
        $rank = $this->makeRank();
        $tag = $this->makeTag();
        $character = Character::factory()->main()->create(['rank_id' => $rank->id]);
        $alt = Character::factory()->create(['rank_id' => null]);
        $this->linkCharacters($character, $alt);
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $this->attachCharacter($report, $character, 1);
        $this->attachCharacter($report, $alt, 1);

        $filters = new FiltersData(includeLinkedCharacters: true);
        $rows = $this->makeDataTable($filters)->rows();

        $names = $rows->pluck('character.name');
        $this->assertTrue($names->contains($alt->name));
    }

    // ==================== resolvedRankIds() Tests ====================

    #[Test]
    public function resolved_rank_ids_returns_counting_rank_ids_when_no_filter_set(): void
    {
        $counting = $this->makeRank(true);
        $this->makeRank(false);

        $rankIds = $this->makeDataTable()->resolvedRankIds();

        $this->assertContains($counting->id, $rankIds);
        $this->assertCount(1, $rankIds);
    }

    #[Test]
    public function resolved_rank_ids_returns_filter_rank_ids_when_filter_is_set(): void
    {
        $rank1 = $this->makeRank();
        $rank2 = $this->makeRank();

        $filters = new FiltersData(rankIds: [$rank1->id]);
        $rankIds = $this->makeDataTable($filters)->resolvedRankIds();

        $this->assertEquals([$rank1->id], $rankIds);
        $this->assertNotContains($rank2->id, $rankIds);
    }

    // ==================== Memoisation Tests ====================

    #[Test]
    public function records_are_memoised_across_columns_and_rows(): void
    {
        $tag = $this->makeTag();
        $report = $this->makeReport($tag, Carbon::parse('2025-01-01 20:00', 'Europe/Paris'));
        $rank = $this->makeRank();
        $character = Character::factory()->create(['rank_id' => $rank->id]);
        $this->attachCharacter($report, $character, 1);

        $table = $this->makeDataTable();

        $columns = $table->columns();
        $rows = $table->rows();

        $this->assertCount(1, $columns);
        $this->assertCount(1, $rows);
    }
}
