<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\BuildAttendanceMatrix;
use App\Data\Attendance\AttendanceMatrixData;
use App\Data\Attendance\CharacterAttendanceData;
use App\Data\Attendance\ReportClusterData;
use App\Enums\Presence;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\WarcraftLogs\Zone;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsAttendanceFixtures;
use Tests\TestCase;

#[Group('raiding')]
class BuildAttendanceMatrixTest extends TestCase
{
    use BuildsAttendanceFixtures;
    use RefreshDatabase;

    private GameVersion $gameVersion;

    private GuildRank $rank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameVersion = $this->createAttendanceVersion();
        $this->rank = $this->createRank($this->gameVersion);
    }

    #[Test]
    public function it_returns_an_empty_matrix_when_the_version_has_no_reports(): void
    {
        $matrix = $this->build();

        $this->assertInstanceOf(AttendanceMatrixData::class, $matrix);
        $this->assertTrue($matrix->columns->isEmpty());
        $this->assertTrue($matrix->rows->isEmpty());
    }

    // ==================== columns ====================

    #[Test]
    public function it_describes_each_column(): void
    {
        $zone = Zone::factory()->create(['name' => 'Karazhan']);
        $report = $this->createReport($this->gameVersion, Carbon::parse('2025-01-07 20:00', 'Europe/Paris'), attributes: ['code' => 'ABC', 'zone_id' => $zone->id]);

        $column = $this->build()->columns->sole();

        $this->assertInstanceOf(ReportClusterData::class, $column);
        $this->assertSame($report->id, $column->id);
        $this->assertSame('ABC', $column->code);
        $this->assertTrue($column->startTime->equalTo(Carbon::parse('2025-01-07 20:00', 'Europe/Paris')));
        $this->assertSame('Karazhan', $column->zoneName);
    }

    #[Test]
    public function it_orders_columns_newest_first(): void
    {
        $older = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $newer = $this->createReport($this->gameVersion, '2025-01-08 20:00');

        $this->assertSame([$newer->id, $older->id], $this->build()->columns->map(fn (ReportClusterData $column): string => $column->id)->all());
    }

    #[Test]
    public function it_merges_linked_reports_into_one_column(): void
    {
        $first = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $second = $this->createReport($this->gameVersion, '2025-01-01 21:00');
        $this->linkReports($first, $second);
        $this->attend($first, $this->createCharacter($this->gameVersion, $this->rank));

        $matrix = $this->build();

        $this->assertCount(1, $matrix->columns);
        $this->assertSame([Presence::Present], $matrix->rows->first()->attendance);
    }

    // ==================== presence and totals ====================

    #[Test]
    public function it_counts_a_single_attended_raid_as_full_attendance(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank);
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $character);

        $row = $this->build()->rows->sole();

        $this->assertInstanceOf(CharacterAttendanceData::class, $row);
        $this->assertTrue($row->character->is($character));
        $this->assertSame(1, $row->totalReports);
        $this->assertSame(1, $row->reportsAttended);
        $this->assertSame(100.0, $row->percentage);
        $this->assertSame([Presence::Present], $row->attendance);
    }

    #[Test]
    public function it_counts_present_and_benched_as_attended(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank);
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $character, Presence::Present);
        $this->attend($this->createReport($this->gameVersion, '2025-01-08 20:00'), $character, Presence::Benched);
        $this->attend($this->createReport($this->gameVersion, '2025-01-15 20:00'), $character, Presence::Absent);

        $row = $this->build()->rows->sole();

        $this->assertSame(3, $row->totalReports);
        $this->assertSame(2, $row->reportsAttended);
        $this->assertSame(66.67, $row->percentage);
        $this->assertSame([Presence::Absent, Presence::Benched, Presence::Present], $row->attendance);
    }

    #[Test]
    public function it_counts_an_absent_presence_as_not_attended(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank);
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $character, Presence::Absent);

        $row = $this->build()->rows->sole();

        $this->assertSame(0.0, $row->percentage);
        $this->assertSame([Presence::Absent], $row->attendance);
    }

    #[Test]
    public function it_counts_an_unknown_presence_value_as_not_attended(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank);
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $character);
        $this->createReport($this->gameVersion, '2025-01-08 20:00')->characters()->attach($character->id, ['presence' => 3]);

        $row = $this->build()->rows->sole();

        $this->assertSame(2, $row->totalReports);
        $this->assertSame(1, $row->reportsAttended);
        $this->assertSame(50.0, $row->percentage);
    }

    #[Test]
    public function it_counts_a_later_raid_without_the_character_as_absent(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank);
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $character);
        $this->createReport($this->gameVersion, '2025-01-08 20:00');

        $row = $this->build()->rows->sole();

        $this->assertSame([Presence::Absent, Presence::Present], $row->attendance);
        $this->assertSame(50.0, $row->percentage);
    }

    #[Test]
    public function it_leaves_raids_before_a_characters_first_cluster_null(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank);
        $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($this->createReport($this->gameVersion, '2025-01-08 20:00'), $character);

        $row = $this->build()->rows->sole();

        $this->assertSame([Presence::Present, null], $row->attendance);
        $this->assertSame(100.0, $row->percentage);
    }

    #[Test]
    public function it_starts_a_characters_row_at_its_own_column_when_start_times_tie(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank);
        $other = $this->createCharacter($this->gameVersion, $this->rank);
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $other);
        $characterReport = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($characterReport, $character);

        $matrix = $this->build();
        $row = $matrix->rows->first(fn (CharacterAttendanceData $row) => $row->character->is($character));
        $characterColumn = $matrix->columns->search(fn (ReportClusterData $column): bool => $column->id === $characterReport->id);

        $this->assertSame(
            $matrix->columns->keys()->map(fn (int $index): ?Presence => match (true) {
                $index < $characterColumn => Presence::Absent,
                $index === $characterColumn => Presence::Present,
                default => null,
            })->all(),
            $row->attendance,
        );
        $this->assertSame($characterColumn + 1, $row->totalReports);
    }

    #[Test]
    public function it_sets_first_attendance_to_the_first_attended_cluster_start(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank);
        $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $first = $this->createReport($this->gameVersion, '2025-01-08 20:00');
        $this->attend($first, $character);
        $this->attend($this->createReport($this->gameVersion, '2025-01-15 20:00'), $character);

        $this->assertTrue($this->build()->rows->sole()->firstAttendance->eq($first->start_time));
    }

    #[Test]
    public function it_tracks_first_attendance_per_character(): void
    {
        $early = $this->createCharacter($this->gameVersion, $this->rank);
        $late = $this->createCharacter($this->gameVersion, $this->rank);
        $first = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $second = $this->createReport($this->gameVersion, '2025-01-08 20:00');
        $this->attend($first, $early);
        $this->attend($second, $late);

        $rows = $this->build()->rows->keyBy(fn (CharacterAttendanceData $row) => $row->character->id);

        $this->assertTrue($rows[$early->id]->firstAttendance->eq($first->start_time));
        $this->assertTrue($rows[$late->id]->firstAttendance->eq($second->start_time));
    }

    // ==================== planned absences ====================

    #[Test]
    public function it_leaves_planned_absence_raids_out_of_the_totals(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank);
        $this->attend($this->createReport($this->gameVersion, Carbon::parse('2025-01-01 20:00', 'Europe/Paris')), $character);
        $this->attend($this->createReport($this->gameVersion, Carbon::parse('2025-01-08 20:00', 'Europe/Paris')), $character, Presence::Absent);
        PlannedAbsence::factory()->create(['character_id' => $character->id, 'start_date' => '2025-01-08', 'end_date' => '2025-01-08']);

        $row = $this->build()->rows->sole();

        $this->assertSame(1, $row->totalReports);
        $this->assertSame(100.0, $row->percentage);
    }

    #[Test]
    public function it_reports_zero_percent_when_every_raid_is_covered_by_an_absence(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank);
        $this->attend($this->createReport($this->gameVersion, Carbon::parse('2025-01-01 20:00', 'Europe/Paris')), $character, Presence::Absent);
        PlannedAbsence::factory()->create(['character_id' => $character->id, 'start_date' => '2025-01-01', 'end_date' => '2025-01-01']);

        $this->assertSame(0.0, $this->build()->rows->sole()->percentage);
    }

    #[Test]
    public function it_records_the_covering_planned_absence_per_raid(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank);
        $this->attend($this->createReport($this->gameVersion, Carbon::parse('2025-01-01 20:00', 'Europe/Paris')), $character, Presence::Absent);
        $absence = PlannedAbsence::factory()->create(['character_id' => $character->id, 'start_date' => '2025-01-01', 'end_date' => '2025-01-01']);

        $this->assertTrue($this->build()->rows->sole()->plannedAbsences[0]->is($absence));
    }

    #[Test]
    public function it_records_null_for_raids_without_a_planned_absence(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank);
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $character);

        $this->assertSame([null], $this->build()->rows->sole()->plannedAbsences);
    }

    #[Test]
    #[DataProvider('plannedAbsenceDateCases')]
    public function it_matches_planned_absences_to_raids_by_date(string $raidStart, string $start, ?string $end, bool $isCovered): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank);
        $this->attend($this->createReport($this->gameVersion, $raidStart), $character, Presence::Absent);
        PlannedAbsence::factory()->create(['character_id' => $character->id, 'start_date' => $start, 'end_date' => $end]);

        $this->assertSame($isCovered, $this->build()->rows->sole()->plannedAbsences[0] !== null);
    }

    /**
     * @return array<string, array{string, string, string|null, bool}>
     */
    public static function plannedAbsenceDateCases(): array
    {
        return [
            'on the first day' => ['2025-01-08 20:00', '2025-01-08', '2025-01-10', true],
            'late on the last day' => ['2025-01-10 23:30', '2025-01-08', '2025-01-10', true],
            'the day before' => ['2025-01-07 20:00', '2025-01-08', '2025-01-10', false],
            'just after midnight following the last day' => ['2025-01-11 00:30', '2025-01-08', '2025-01-10', false],
            'open-ended, months later' => ['2025-06-01 20:00', '2025-01-08', null, true],
        ];
    }

    // ==================== rows ====================

    #[Test]
    public function it_builds_one_row_per_character(): void
    {
        $report = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        foreach (range(1, 3) as $ignored) {
            $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank));
        }

        $this->assertCount(3, $this->build()->rows);
    }

    #[Test]
    public function it_sorts_rows_by_character_name(): void
    {
        $report = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank, ['name' => 'Zebra']));
        $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank, ['name' => 'Alpha']));

        $this->assertSame(['Alpha', 'Zebra'], $this->build()->rows->map(fn ($row) => $row->character->name)->all());
    }

    #[Test]
    public function it_keeps_same_named_characters_apart(): void
    {
        $report = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $thrall = $this->createCharacter($this->gameVersion, $this->rank, ['name' => 'Thrall']);
        $otherVersion = $this->createAttendanceVersion();
        $otherRank = $this->createRank($otherVersion);
        $otherThrall = $this->createCharacter($otherVersion, $otherRank, ['name' => 'Thrall']);
        $this->attend($report, $thrall);
        $this->attend($report, $otherThrall, Presence::Absent);

        $rows = BuildAttendanceMatrix::run($this->gameVersion, rankIds: [$this->rank->id, $otherRank->id])->rows->keyBy(fn ($row) => $row->character->id);

        $this->assertSame([Presence::Present], $rows[$thrall->id]->attendance);
        $this->assertSame([Presence::Absent], $rows[$otherThrall->id]->attendance);
    }

    #[Test]
    #[DataProvider('combineLinkedCharactersCases')]
    public function it_drops_the_pivot_from_row_characters(bool $combineLinkedCharacters): void
    {
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]));

        $character = BuildAttendanceMatrix::run($this->gameVersion, combineLinkedCharacters: $combineLinkedCharacters)->rows->sole()->character;

        $this->assertArrayNotHasKey('pivot', $character->getRelations());
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function combineLinkedCharactersCases(): array
    {
        return [
            'separate characters' => [false],
            'combined characters' => [true],
        ];
    }

    // ==================== ranks ====================

    #[Test]
    public function it_defaults_to_the_versions_counting_ranks(): void
    {
        $report = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($report, $counted = $this->createCharacter($this->gameVersion, $this->rank));
        $this->attend($report, $this->createCharacter($this->gameVersion, $this->createRank($this->gameVersion, countsAttendance: false)));

        $this->assertSame([$counted->id], $this->build()->rows->map(fn ($row) => $row->character->id)->all());
    }

    #[Test]
    public function it_ignores_another_versions_counting_ranks(): void
    {
        $otherRank = $this->createRank($this->createAttendanceVersion());
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $this->createCharacter($this->gameVersion, $otherRank));

        $this->assertTrue($this->build()->rows->isEmpty());
    }

    #[Test]
    public function it_limits_rows_to_the_given_ranks(): void
    {
        $other = $this->createRank($this->gameVersion);
        $report = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($report, $kept = $this->createCharacter($this->gameVersion, $this->rank));
        $this->attend($report, $this->createCharacter($this->gameVersion, $other));

        $rows = BuildAttendanceMatrix::run($this->gameVersion, rankIds: [$this->rank->id])->rows;

        $this->assertSame([$kept->id], $rows->map(fn ($row) => $row->character->id)->all());
    }

    #[Test]
    public function it_returns_no_rows_when_the_version_has_no_counting_ranks(): void
    {
        $this->rank->update(['count_attendance' => false]);
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $this->createCharacter($this->gameVersion, $this->rank));

        $matrix = $this->build();

        $this->assertCount(1, $matrix->columns);
        $this->assertTrue($matrix->rows->isEmpty());
    }

    #[Test]
    #[DataProvider('combineLinkedCharactersCases')]
    public function it_returns_no_rows_for_an_empty_rank_list(bool $combineLinkedCharacters): void
    {
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]));

        $matrix = BuildAttendanceMatrix::run($this->gameVersion, rankIds: [], combineLinkedCharacters: $combineLinkedCharacters);

        $this->assertTrue($matrix->rows->isEmpty());
    }

    #[Test]
    public function it_returns_no_columns_for_an_empty_guild_tag_list(): void
    {
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $this->createCharacter($this->gameVersion, $this->rank));

        $matrix = BuildAttendanceMatrix::run($this->gameVersion, guildTagIds: []);

        $this->assertTrue($matrix->columns->isEmpty());
        $this->assertTrue($matrix->rows->isEmpty());
    }

    // ==================== version scope ====================

    #[Test]
    public function it_excludes_another_versions_reports(): void
    {
        $character = $this->createCharacter($this->gameVersion, $this->rank);
        $this->attend($this->createReport($this->gameVersion, '2025-01-01 20:00'), $character);
        $this->createReport($this->createAttendanceVersion(), '2025-01-08 20:00');

        $matrix = $this->build();

        $this->assertCount(1, $matrix->columns);
        $this->assertSame(100.0, $matrix->rows->sole()->percentage);
    }

    // ==================== combining ====================

    #[Test]
    public function it_folds_an_unranked_alt_into_its_ranked_main_when_combining(): void
    {
        $main = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        $alt = $this->createCharacter($this->gameVersion, null, ['is_main' => false]);
        $this->linkCharacters($main, $alt);
        $report = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($report, $main, Presence::Absent);
        $this->attend($report, $alt);

        $row = BuildAttendanceMatrix::run($this->gameVersion, combineLinkedCharacters: true)->rows->sole();

        $this->assertTrue($row->character->is($main));
        $this->assertSame([Presence::Present], $row->attendance);
        $this->assertSame([[$alt->name]], $row->attendanceNames);
    }

    #[Test]
    public function it_runs_a_query_count_that_does_not_grow_with_rows(): void
    {
        $report = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank));
        $oneRow = $this->countQueries(fn () => $this->build());

        foreach (range(1, 3) as $ignored) {
            $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank));
        }

        $this->assertSame($oneRow, $this->countQueries(fn () => $this->build()));
    }

    // ==================== character ====================

    #[Test]
    #[DataProvider('combineLinkedCharactersCases')]
    public function it_narrows_the_rows_to_the_given_character(bool $combineLinkedCharacters): void
    {
        $report = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($report, $kept = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]));
        $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]));

        $matrix = BuildAttendanceMatrix::run($this->gameVersion, combineLinkedCharacters: $combineLinkedCharacters, character: $kept);

        $this->assertTrue($matrix->rows->sole()->character->is($kept));
        $this->assertCount(1, $matrix->columns);
    }

    #[Test]
    public function it_narrows_the_rows_to_an_alts_main_when_combining(): void
    {
        $main = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        $alt = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => false]);
        $this->linkCharacters($main, $alt);
        $report = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($report, $alt);
        $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]));

        $row = BuildAttendanceMatrix::run($this->gameVersion, combineLinkedCharacters: true, character: $alt)->rows->sole();

        $this->assertTrue($row->character->is($main));
    }

    #[Test]
    public function it_narrows_the_rows_to_the_alt_itself_when_not_combining(): void
    {
        $main = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        $alt = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => false]);
        $this->linkCharacters($main, $alt);
        $report = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($report, $main);
        $this->attend($report, $alt);

        $row = BuildAttendanceMatrix::run($this->gameVersion, character: $alt)->rows->sole();

        $this->assertTrue($row->character->is($alt));
    }

    #[Test]
    public function it_returns_no_rows_for_an_alt_without_a_main_when_combining(): void
    {
        $alt = $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => false]);
        $report = $this->createReport($this->gameVersion, '2025-01-01 20:00');
        $this->attend($report, $alt);
        $this->attend($report, $this->createCharacter($this->gameVersion, $this->rank, ['is_main' => true]));

        $this->assertTrue(BuildAttendanceMatrix::run($this->gameVersion, combineLinkedCharacters: true, character: $alt)->rows->isEmpty());
    }

    // ==================== helpers ====================

    private function build(): AttendanceMatrixData
    {
        return BuildAttendanceMatrix::run($this->gameVersion);
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
