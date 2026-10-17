<?php

namespace Tests\Feature\Data\Attendance;

use App\Actions\Attendance\BuildAttendanceMatrix;
use App\Data\Attendance\CharacterAttendanceData;
use App\Enums\PlannedAbsenceReason;
use App\Enums\Presence;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsAttendanceFixtures;
use Tests\TestCase;

#[Group('raiding')]
class CharacterAttendanceDataTest extends TestCase
{
    use BuildsAttendanceFixtures;
    use RefreshDatabase;

    // ==================== characterWithClassOnly ====================

    #[Test]
    public function character_with_class_only_keeps_only_the_playable_class_relation(): void
    {
        $character = Character::factory()
            ->withPlayableClass(PlayableClass::factory()->create(['name' => 'Druid']))
            ->create(['rank_id' => GuildRank::factory()->create()->id])
            ->load(['playableClass', 'rank']);

        $result = $this->row($character)->characterWithClassOnly();

        $this->assertSame(['playableClass'], array_keys($result->getRelations()));
        $this->assertSame('Druid', $result->playableClass->name);
        $this->assertSame($character->id, $result->id);
    }

    #[Test]
    public function character_with_class_only_does_not_mutate_the_row_character(): void
    {
        $character = Character::factory()
            ->create(['rank_id' => GuildRank::factory()->create()->id])
            ->load(['playableClass', 'rank']);

        $this->row($character)->characterWithClassOnly();

        $this->assertTrue($character->relationLoaded('rank'));
    }

    #[Test]
    public function character_with_class_only_keeps_a_null_playable_class(): void
    {
        $character = Character::factory()->create(['playable_class_id' => null])->load('playableClass');

        $result = $this->row($character)->characterWithClassOnly();

        $this->assertTrue($result->relationLoaded('playableClass'));
        $this->assertNull($result->playableClass);
    }

    // ==================== recentAttendanceRate ====================

    /**
     * @param  array<int, Presence|null>  $attendance
     */
    #[Test]
    #[DataProvider('recentRateCases')]
    public function recent_attendance_rate_uses_the_newest_four_non_null_raids(array $attendance, ?float $expected): void
    {
        $this->assertSame($expected, $this->row(Character::factory()->withoutParents()->make(['id' => 1]), attendance: $attendance)->recentAttendanceRate());
    }

    /**
     * @return array<string, array{array<int, Presence|null>, float|null}>
     */
    public static function recentRateCases(): array
    {
        $p = Presence::Present;
        $b = Presence::Benched;
        $a = Presence::Absent;

        return [
            'fewer than three raids' => [[$p, $p, null], null],
            'exactly three raids' => [[$p, $a, $a], 1 / 3 * 100],
            'only the newest four count' => [[$a, $a, $a, $a, $p, $p], 0.0],
            'nulls are skipped' => [[null, $p, null, $p, $a, $p], 75.0],
            'benched counts as attended' => [[$b, $b, $a, $a], 50.0],
        ];
    }

    // ==================== toArray ====================

    #[Test]
    public function it_emits_only_the_keys_the_matrix_reads(): void
    {
        $this->assertEqualsCanonicalizing(
            ['character', 'percentage', 'attendance', 'planned_absences'],
            array_keys($this->row()->toArray()),
        );
    }

    #[Test]
    public function it_emits_the_percentage_and_presence_cases(): void
    {
        $array = $this->row(reportsAttended: 151, totalReports: 200, attendance: [Presence::Present, Presence::Benched, null])->toArray();

        $this->assertSame(75.5, $array['percentage']);
        $this->assertSame([Presence::Present, Presence::Benched, null], $array['attendance']);
    }

    #[Test]
    public function it_emits_the_character_in_its_attendance_shape_with_rank_and_playable_class(): void
    {
        $rank = GuildRank::factory()->create(['name' => 'Raider']);
        $character = Character::factory()
            ->withPlayableClass(PlayableClass::factory()->create(['name' => 'Druid']))
            ->create(['name' => 'Thrall', 'rank_id' => $rank->id])
            ->load(['playableClass', 'rank']);

        $array = $this->row($character)->toArray()['character'];

        $this->assertSame(['id', 'name', 'playable_class', 'rank'], array_keys($array));
        $this->assertSame($character->id, $array['id']);
        $this->assertSame('Thrall', $array['name']);
        $this->assertSame(['name' => 'Raider', 'slug' => 'raider'], $array['rank']);
        $this->assertSame(['id', 'name', 'icon_url'], array_keys($array['playable_class']));
        $this->assertSame('Druid', $array['playable_class']['name']);
    }

    #[Test]
    public function it_emits_null_playable_class_for_a_character_without_one(): void
    {
        $character = Character::factory()->create(['playable_class_id' => null])->load('playableClass');

        $this->assertNull($this->row($character)->toArray()['character']['playable_class']);
    }

    #[Test]
    public function it_emits_each_planned_absence_in_its_attendance_shape_keyed_by_raid_column(): void
    {
        $absence = PlannedAbsence::factory()->create(['reason' => PlannedAbsenceReason::Work, 'notes' => 'Late shift.']);

        $plannedAbsences = $this->row(attendance: [Presence::Present, Presence::Absent], plannedAbsences: [null, $absence])->toArray()['planned_absences'];

        $this->assertSame([1], array_keys($plannedAbsences));
        $this->assertSame(['reason', 'notes'], array_keys($plannedAbsences[1]));
        $this->assertSame('Stuck at work', $plannedAbsences[1]['reason']->label);
        $this->assertSame('briefcase', $plannedAbsences[1]['reason']->icon);
        $this->assertSame('Late shift.', $plannedAbsences[1]['notes']);
    }

    #[Test]
    public function it_derives_the_percentage_rather_than_reading_it_from_input(): void
    {
        $row = CharacterAttendanceData::from([
            'character' => Character::factory()->create()->load('playableClass'),
            'first_attendance' => Carbon::parse('2025-01-01 19:00', 'UTC'),
            'total_reports' => 4,
            'reports_attended' => 3,
            'attendance' => [],
            'planned_absences' => [],
            'percentage' => 10.0,
        ]);

        $this->assertSame(75.0, $row->percentage);
    }

    #[Test]
    public function it_emits_the_attendance_names(): void
    {
        $row = new CharacterAttendanceData(
            character: Character::factory()->create()->load('playableClass'),
            firstAttendance: Carbon::parse('2025-01-01 19:00', 'UTC'),
            totalReports: 1,
            reportsAttended: 1,
            attendance: [Presence::Present],
            plannedAbsences: [null],
            attendanceNames: [['Thrall', 'Go\'el']],
        );

        $this->assertSame([['Thrall', 'Go\'el']], $row->toArray()['attendance_names']);
    }

    #[Test]
    public function it_omits_the_attendance_names_by_default(): void
    {
        $this->assertArrayNotHasKey('attendance_names', $this->row()->toArray());
    }

    #[Test]
    public function it_runs_no_query_to_serialize_matrix_rows(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $rank = $this->createRank($gameVersion);
        $report = $this->createReport($gameVersion, '2025-01-01 20:00');
        foreach (range(1, 3) as $ignored) {
            $this->attend($report, $this->createCharacter($gameVersion, $rank, ['playable_class_id' => PlayableClass::factory()->create()->id]));
        }
        $rows = BuildAttendanceMatrix::run($gameVersion)->rows;

        DB::enableQueryLog();
        $rows->toArray();

        $this->assertSame([], DB::getQueryLog());
    }

    // ==================== helpers ====================

    /**
     * @param  array<int, Presence|null>  $attendance
     * @param  array<int, PlannedAbsence|null>|null  $plannedAbsences
     */
    private function row(?Character $character = null, int $reportsAttended = 0, int $totalReports = 0, array $attendance = [], ?array $plannedAbsences = null): CharacterAttendanceData
    {
        return new CharacterAttendanceData(
            character: $character ?? Character::factory()->create()->load('playableClass'),
            firstAttendance: Carbon::parse('2025-01-01 19:00', 'UTC'),
            totalReports: $totalReports,
            reportsAttended: $reportsAttended,
            attendance: $attendance,
            plannedAbsences: $plannedAbsences ?? array_fill(0, count($attendance), null),
        );
    }
}
