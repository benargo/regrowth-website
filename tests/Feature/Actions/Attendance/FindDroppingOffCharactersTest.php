<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\FindDroppingOffCharacters;
use App\Data\Attendance\CharacterAttendanceData;
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
    public function it_finds_a_character_whose_recent_rate_fell_more_than_25_points(): void
    {
        $dropping = $this->row(3, 4, [Presence::Absent, Presence::Absent, Presence::Absent, Presence::Present]);

        $this->assertSame([$dropping], FindDroppingOffCharacters::run(collect([$dropping]))->all());
    }

    #[Test]
    public function it_includes_a_drop_of_exactly_25_points(): void
    {
        $row = $this->row(3, 4, [Presence::Present, Presence::Present, Presence::Absent, Presence::Absent]);

        $this->assertCount(1, FindDroppingOffCharacters::run(collect([$row])));
    }

    #[Test]
    public function it_ignores_a_drop_of_24_points(): void
    {
        $row = $this->row(6, 25, [Presence::Absent, Presence::Absent, Presence::Absent, Presence::Absent]);

        $this->assertTrue(FindDroppingOffCharacters::run(collect([$row]))->isEmpty());
    }

    #[Test]
    public function it_ignores_a_drop_under_25_points(): void
    {
        $row = $this->row(4, 5, [Presence::Present, Presence::Present, Presence::Present, Presence::Absent]);

        $this->assertTrue(FindDroppingOffCharacters::run(collect([$row]))->isEmpty());
    }

    #[Test]
    public function it_ignores_a_character_with_fewer_than_three_recent_raids(): void
    {
        $row = $this->row(1, 1, [Presence::Absent, Presence::Absent, null]);

        $this->assertTrue(FindDroppingOffCharacters::run(collect([$row]))->isEmpty());
    }

    // ==================== helpers ====================

    /**
     * @param  array<int, Presence|null>  $attendance
     */
    private function row(int $reportsAttended, int $totalReports, array $attendance = []): CharacterAttendanceData
    {
        return new CharacterAttendanceData(
            character: Character::factory()->withoutParents()->make(),
            firstAttendance: Carbon::parse('2025-01-01 19:00', 'UTC'),
            totalReports: $totalReports,
            reportsAttended: $reportsAttended,
            attendance: $attendance,
            plannedAbsences: array_fill(0, count($attendance), null),
        );
    }
}
