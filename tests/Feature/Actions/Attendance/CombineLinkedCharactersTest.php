<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\CombineLinkedCharacters;
use App\Data\Attendance\CharacterAttendanceData;
use App\Enums\Presence;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsAttendanceFixtures;
use Tests\TestCase;

#[Group('raiding')]
class CombineLinkedCharactersTest extends TestCase
{
    use BuildsAttendanceFixtures;
    use RefreshDatabase;

    private GameVersion $gameVersion;

    private GuildRank $rank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameVersion = $this->makeAttendanceVersion();
        $this->rank = $this->makeRank($this->gameVersion);
    }

    // ==================== folding ====================

    #[Test]
    public function it_folds_an_alts_attendance_into_its_main_by_priority(): void
    {
        [$main, $alt] = $this->mainWithAlt();

        $rows = $this->combine([
            $this->row($main, [Presence::Absent, Presence::Benched, Presence::Absent, null]),
            $this->row($alt, [Presence::Present, Presence::Absent, Presence::Absent, null]),
        ], raidCount: 4);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]->character->is($main));
        $this->assertSame([Presence::Present, Presence::Benched, Presence::Absent, null], $rows[0]->attendance);
    }

    #[Test]
    public function it_lists_the_characters_who_attended_each_raid(): void
    {
        [$main, $alt] = $this->mainWithAlt();

        $rows = $this->combine([
            $this->row($main, [Presence::Present, Presence::Absent, Presence::Absent]),
            $this->row($alt, [Presence::Benched, Presence::Present, Presence::Absent]),
        ], raidCount: 3);

        $this->assertSame([[$main->name, $alt->name], [$alt->name], []], $rows[0]->attendanceNames);
    }

    #[Test]
    public function it_recounts_the_totals_and_keeps_the_earliest_first_attendance(): void
    {
        [$main, $alt] = $this->mainWithAlt();

        $rows = $this->combine([
            $this->row($main, [Presence::Absent, Presence::Absent, null], firstAttendance: '2025-01-08 19:00'),
            $this->row($alt, [Presence::Present, Presence::Absent, Presence::Absent], firstAttendance: '2025-01-01 19:00'),
        ], raidCount: 3);

        $this->assertSame(3, $rows[0]->totalReports);
        $this->assertSame(1, $rows[0]->reportsAttended);
        $this->assertSame(33.33, $rows[0]->percentage);
        $this->assertTrue($rows[0]->firstAttendance->eq(Carbon::parse('2025-01-01 19:00', 'UTC')));
    }

    #[Test]
    public function it_records_zero_attendance_when_every_merged_character_was_absent(): void
    {
        [$main, $alt] = $this->mainWithAlt();

        $rows = $this->combine([
            $this->row($main, [Presence::Absent]),
            $this->row($alt, [Presence::Absent]),
        ], raidCount: 1);

        $this->assertSame([Presence::Absent], $rows[0]->attendance);
        $this->assertSame(0.0, $rows[0]->percentage);
    }

    // ==================== planned absences ====================

    #[Test]
    public function it_keeps_only_the_mains_planned_absences_and_skips_only_those(): void
    {
        [$main, $alt] = $this->mainWithAlt();
        $mainAbsence = PlannedAbsence::factory()->create(['character_id' => $main->id]);
        $altAbsence = PlannedAbsence::factory()->create(['character_id' => $alt->id]);

        $rows = $this->combine([
            $this->row($main, [Presence::Absent, Presence::Absent], plannedAbsences: [$mainAbsence, null]),
            $this->row($alt, [Presence::Absent, Presence::Absent], plannedAbsences: [null, $altAbsence]),
        ], raidCount: 2);

        $this->assertSame([$mainAbsence, null], $rows[0]->plannedAbsences);
        $this->assertSame(1, $rows[0]->totalReports);
    }

    // ==================== mains ====================

    #[Test]
    public function it_builds_a_row_for_a_main_missing_from_the_rows(): void
    {
        [$main, $alt] = $this->mainWithAlt();

        $rows = $this->combine([
            $this->row($alt, [Presence::Present, null], firstAttendance: '2025-01-08 19:00'),
        ], raidCount: 2);

        $this->assertCount(1, $rows);
        $this->assertTrue($rows[0]->character->is($main));
        $this->assertTrue($rows[0]->character->relationLoaded('playableClass'));
        $this->assertTrue($rows[0]->character->relationLoaded('rank'));
        $this->assertSame([Presence::Present, null], $rows[0]->attendance);
        $this->assertSame(1, $rows[0]->totalReports);
        $this->assertTrue($rows[0]->firstAttendance->eq(Carbon::parse('2025-01-08 19:00', 'UTC')));
    }

    #[Test]
    public function it_outputs_only_mains_in_the_given_ranks(): void
    {
        $otherRank = $this->makeRank($this->gameVersion);
        $ranked = $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        $unranked = $this->makeCharacter($this->gameVersion, $otherRank, ['is_main' => true]);

        $rows = $this->combine([
            $this->row($ranked, [Presence::Present]),
            $this->row($unranked, [Presence::Present]),
        ], raidCount: 1);

        $this->assertEquals([$ranked->id], $rows->map(fn ($row) => $row->character->id)->all());
    }

    #[Test]
    public function it_drops_non_main_rows_without_a_main(): void
    {
        $loner = $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => false]);

        $this->assertTrue($this->combine([$this->row($loner, [Presence::Present])], raidCount: 1)->isEmpty());
    }

    #[Test]
    public function it_sorts_the_combined_rows_by_name(): void
    {
        $zed = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Zed', 'is_main' => true]);
        $amy = $this->makeCharacter($this->gameVersion, $this->rank, ['name' => 'Amy', 'is_main' => true]);

        $rows = $this->combine([$this->row($zed, [Presence::Present]), $this->row($amy, [Presence::Present])], raidCount: 1);

        $this->assertSame(['Amy', 'Zed'], $rows->map(fn ($row) => $row->character->name)->all());
    }

    // ==================== helpers ====================

    /**
     * @return array{Character, Character}
     */
    private function mainWithAlt(): array
    {
        $main = $this->makeCharacter($this->gameVersion, $this->rank, ['is_main' => true]);
        $alt = $this->makeCharacter($this->gameVersion, null, ['is_main' => false]);
        $this->linkCharacters($main, $alt);

        return [$main, $alt];
    }

    /**
     * @param  array<int, CharacterAttendanceData>  $rows
     */
    private function combine(array $rows, int $raidCount): Collection
    {
        return CombineLinkedCharacters::run(collect($rows), [$this->rank->id], $raidCount);
    }

    /**
     * @param  array<int, Presence|null>  $attendance
     * @param  array<int, PlannedAbsence|null>|null  $plannedAbsences
     */
    private function row(Character $character, array $attendance, ?array $plannedAbsences = null, string $firstAttendance = '2025-01-01 19:00'): CharacterAttendanceData
    {
        return new CharacterAttendanceData(
            character: $character->fresh(['rank', 'linkedCharacters', 'playableClass.media']),
            firstAttendance: Carbon::parse($firstAttendance, 'UTC'),
            totalReports: 0,
            reportsAttended: 0,
            attendance: $attendance,
            plannedAbsences: $plannedAbsences ?? array_fill(0, count($attendance), null),
        );
    }
}
