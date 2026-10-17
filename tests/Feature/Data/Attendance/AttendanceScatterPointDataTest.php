<?php

namespace Tests\Feature\Data\Attendance;

use App\Actions\Attendance\BuildAttendanceMatrix;
use App\Data\Attendance\AttendanceScatterPointData;
use App\Data\Attendance\CharacterAttendanceData;
use App\Enums\Presence;
use App\Models\Character;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsAttendanceFixtures;
use Tests\TestCase;

#[Group('raiding')]
class AttendanceScatterPointDataTest extends TestCase
{
    use BuildsAttendanceFixtures;
    use RefreshDatabase;

    #[Test]
    public function it_emits_todays_camel_case_keys(): void
    {
        $this->assertSame(
            ['character', 'percentage', 'raidsTotal', 'raidsAttended', 'benched', 'plannedAbsences', 'otherAbsences'],
            array_keys(AttendanceScatterPointData::fromRow($this->row())->toArray()),
        );
    }

    #[Test]
    public function it_emits_the_percentage(): void
    {
        $this->assertSame(0.8, AttendanceScatterPointData::fromRow($this->row(reportsAttended: 1, totalReports: 125))->toArray()['percentage']);
    }

    #[Test]
    public function it_counts_the_presence_cases(): void
    {
        $absence = PlannedAbsence::factory()->create();

        $array = AttendanceScatterPointData::fromRow($this->row(
            attendance: [Presence::Present, Presence::Benched, Presence::Absent, null, Presence::Present],
            plannedAbsences: [null, null, $absence, null, null],
        ))->toArray();

        $this->assertSame(4, $array['raidsTotal']);
        $this->assertSame(2, $array['raidsAttended']);
        $this->assertSame(1, $array['benched']);
        $this->assertSame(1, $array['otherAbsences']);
        $this->assertSame(1, $array['plannedAbsences']);
    }

    #[Test]
    public function it_returns_zero_counts_for_empty_attendance(): void
    {
        $array = AttendanceScatterPointData::fromRow($this->row())->toArray();

        foreach (['raidsTotal', 'raidsAttended', 'benched', 'plannedAbsences', 'otherAbsences'] as $key) {
            $this->assertSame(0, $array[$key]);
        }
    }

    #[Test]
    public function it_emits_the_character_through_character_resource(): void
    {
        $character = Character::factory()->create(['name' => 'Illidan']);

        $emitted = AttendanceScatterPointData::fromRow($this->row($character->load('playableClass')))->toArray()['character'];

        $this->assertSame($character->id, $emitted['id']);
        $this->assertSame('Illidan', $emitted['name']);
        $this->assertSame('illidan', $emitted['slug']);
    }

    #[Test]
    public function it_emits_the_characters_playable_class(): void
    {
        $character = Character::factory()->withPlayableClass(PlayableClass::factory()->create(['name' => 'Rogue']))->create()
            ->load('playableClass');

        $emitted = AttendanceScatterPointData::fromRow($this->row($character))->toArray()['character'];

        $this->assertSame('Rogue', $emitted['playable_class']['name']);
        $this->assertArrayHasKey('icon_url', $emitted['playable_class']);
    }

    #[Test]
    public function it_runs_no_query_to_build_the_payload(): void
    {
        $gameVersion = $this->createAttendanceVersion();
        $rank = $this->createRank($gameVersion);
        $report = $this->createReport($gameVersion, '2025-01-01 20:00');
        foreach (range(1, 3) as $ignored) {
            $this->attend($report, $this->createCharacter($gameVersion, $rank, ['playable_class_id' => PlayableClass::factory()->create()->id]));
        }
        $rows = BuildAttendanceMatrix::run($gameVersion)->rows;

        DB::enableQueryLog();
        AttendanceScatterPointData::collect($rows)->toArray();

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
