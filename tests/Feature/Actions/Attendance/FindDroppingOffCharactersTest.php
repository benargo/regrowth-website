<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\FindDroppingOffCharacters;
use App\Data\Attendance\CharacterAttendanceRowData;
use App\Enums\Presence;
use App\Models\Character;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class FindDroppingOffCharactersTest extends TestCase
{
    #[Test]
    public function it_finds_a_character_whose_recent_rate_fell_at_least_25_points(): void
    {
        $dropping = $this->row(75.0, [Presence::Absent, Presence::Absent, Presence::Present, Presence::Present]);

        $this->assertSame([$dropping], FindDroppingOffCharacters::run(collect([$dropping]))->all());
    }

    #[Test]
    public function it_includes_a_drop_of_exactly_25_points(): void
    {
        $row = $this->row(75.0, [Presence::Present, Presence::Present, Presence::Absent, Presence::Absent]);

        $this->assertCount(1, FindDroppingOffCharacters::run(collect([$row])));
    }

    #[Test]
    public function it_ignores_an_overall_rate_below_25(): void
    {
        $row = $this->row(24.0, [Presence::Absent, Presence::Absent, Presence::Absent, Presence::Absent]);

        $this->assertTrue(FindDroppingOffCharacters::run(collect([$row]))->isEmpty());
    }

    #[Test]
    public function it_ignores_a_character_with_fewer_than_three_recent_raids(): void
    {
        $row = $this->row(100.0, [Presence::Absent, Presence::Absent, null]);

        $this->assertTrue(FindDroppingOffCharacters::run(collect([$row]))->isEmpty());
    }

    // ==================== helpers ====================

    /**
     * @param  array<int, Presence|null>  $attendance
     */
    private function row(float $percentage, array $attendance = []): CharacterAttendanceRowData
    {
        return new CharacterAttendanceRowData(
            character: Character::factory()->withoutParents()->make(),
            firstAttendance: Carbon::parse('2025-01-01 19:00', 'UTC'),
            totalReports: 0,
            reportsAttended: 0,
            percentage: $percentage,
            attendance: $attendance,
            plannedAbsences: array_fill(0, count($attendance), null),
        );
    }
}
