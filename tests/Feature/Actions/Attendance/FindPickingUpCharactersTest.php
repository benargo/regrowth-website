<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\FindPickingUpCharacters;
use App\Data\Attendance\CharacterAttendanceRowData;
use App\Enums\Presence;
use App\Models\Character;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class FindPickingUpCharactersTest extends TestCase
{
    #[Test]
    public function it_finds_a_character_whose_recent_rate_rose_at_least_25_points(): void
    {
        $row = $this->row(50.0, [Presence::Present, Presence::Present, Presence::Present, Presence::Absent]);

        $this->assertSame([$row], FindPickingUpCharacters::run(collect([$row]))->all());
    }

    #[Test]
    public function it_ignores_an_overall_rate_above_75(): void
    {
        $row = $this->row(76.0, [Presence::Present, Presence::Present, Presence::Present, Presence::Present]);

        $this->assertTrue(FindPickingUpCharacters::run(collect([$row]))->isEmpty());
    }

    #[Test]
    public function it_ignores_a_rise_under_25_points(): void
    {
        $row = $this->row(60.0, [Presence::Present, Presence::Present, Presence::Present, Presence::Absent]);

        $this->assertTrue(FindPickingUpCharacters::run(collect([$row]))->isEmpty());
    }

    #[Test]
    public function it_ignores_a_character_with_fewer_than_three_recent_raids(): void
    {
        $row = $this->row(0.0, [Presence::Present, Presence::Present]);

        $this->assertTrue(FindPickingUpCharacters::run(collect([$row]))->isEmpty());
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
