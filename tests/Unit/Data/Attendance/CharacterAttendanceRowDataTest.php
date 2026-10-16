<?php

namespace Tests\Unit\Data\Attendance;

use App\Data\Attendance\CharacterAttendanceRowData;
use App\Enums\Presence;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class CharacterAttendanceRowDataTest extends TestCase
{
    #[Test]
    public function it_exposes_its_properties(): void
    {
        $character = Character::factory()->withoutParents()->make(['id' => 1]);
        $absence = PlannedAbsence::factory()->withoutParents()->make();
        $firstAttendance = Carbon::parse('2025-01-01 19:00', 'UTC');

        $row = new CharacterAttendanceRowData(
            character: $character,
            firstAttendance: $firstAttendance,
            totalReports: 3,
            reportsAttended: 2,
            percentage: 66.67,
            attendance: [Presence::Present, null],
            plannedAbsences: [$absence, null],
            attendanceNames: [['Thrall'], []],
        );

        $this->assertSame($character, $row->character);
        $this->assertSame($firstAttendance, $row->firstAttendance);
        $this->assertSame(3, $row->totalReports);
        $this->assertSame(2, $row->reportsAttended);
        $this->assertSame(66.67, $row->percentage);
        $this->assertSame([Presence::Present, null], $row->attendance);
        $this->assertSame([$absence, null], $row->plannedAbsences);
        $this->assertSame([['Thrall'], []], $row->attendanceNames);
    }

    #[Test]
    public function attendance_names_defaults_to_null(): void
    {
        $this->assertNull($this->row([])->attendanceNames);
    }

    // ==================== recentAttendanceRate ====================

    /**
     * @param  array<int, Presence|null>  $attendance
     */
    #[Test]
    #[DataProvider('recentRateCases')]
    public function recent_attendance_rate_uses_the_newest_four_non_null_raids(array $attendance, ?float $expected): void
    {
        $this->assertSame($expected, $this->row($attendance)->recentAttendanceRate());
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

    // ==================== characterWithPlayableClass ====================

    #[Test]
    public function character_with_playable_class_keeps_only_that_relation(): void
    {
        $playableClass = PlayableClass::factory()->make(['id' => 1]);
        $character = Character::factory()->withoutParents()->make(['id' => 1])
            ->setRelation('playableClass', $playableClass)
            ->setRelation('rank', GuildRank::factory()->make())
            ->setRelation('linkedCharacters', new EloquentCollection);

        $copy = $this->row([], $character)->characterWithPlayableClass();

        $this->assertSame(['playableClass'], array_keys($copy->getRelations()));
        $this->assertSame($playableClass, $copy->playableClass);
        $this->assertTrue($character->relationLoaded('rank'));
    }

    // ==================== helpers ====================

    /**
     * @param  array<int, Presence|null>  $attendance
     */
    private function row(array $attendance, ?Character $character = null): CharacterAttendanceRowData
    {
        return new CharacterAttendanceRowData(
            character: $character ?? Character::factory()->withoutParents()->make(['id' => 1]),
            firstAttendance: Carbon::parse('2025-01-01 19:00', 'UTC'),
            totalReports: 0,
            reportsAttended: 0,
            percentage: 0.0,
            attendance: $attendance,
            plannedAbsences: array_fill(0, count($attendance), null),
        );
    }
}
