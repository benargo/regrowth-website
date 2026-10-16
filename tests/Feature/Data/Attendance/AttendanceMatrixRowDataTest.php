<?php

namespace Tests\Feature\Data\Attendance;

use App\Actions\Attendance\BuildAttendanceMatrix;
use App\Data\Attendance\AttendanceMatrixRowData;
use App\Data\Attendance\CharacterAttendanceRowData;
use App\Enums\Presence;
use App\Models\Character;
use App\Models\GuildRank;
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
class AttendanceMatrixRowDataTest extends TestCase
{
    use BuildsAttendanceFixtures;
    use RefreshDatabase;

    #[Test]
    public function it_emits_todays_snake_case_keys(): void
    {
        $this->assertSame(
            ['id', 'name', 'rank_id', 'percentage', 'attendance', 'planned_absences', 'playable_class'],
            array_keys(AttendanceMatrixRowData::fromRow($this->row())->toArray()),
        );
    }

    #[Test]
    public function it_emits_the_row_values_with_presence_cases(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);

        $array = AttendanceMatrixRowData::fromRow($this->row($character, 75.5, [Presence::Present, Presence::Benched, null]))->toArray();

        $this->assertSame($character->id, $array['id']);
        $this->assertSame('Thrall', $array['name']);
        $this->assertSame($rank->id, $array['rank_id']);
        $this->assertSame(75.5, $array['percentage']);
        $this->assertSame([Presence::Present, Presence::Benched, null], $array['attendance']);
    }

    #[Test]
    public function it_maps_planned_absences_to_their_ids(): void
    {
        $absence = PlannedAbsence::factory()->create();

        $array = AttendanceMatrixRowData::fromRow($this->row(attendance: [Presence::Absent, Presence::Present], plannedAbsences: [$absence, null]))->toArray();

        $this->assertSame([$absence->id, null], $array['planned_absences']);
    }

    #[Test]
    public function it_emits_an_empty_planned_absence_list_without_raids(): void
    {
        $this->assertSame([], AttendanceMatrixRowData::fromRow($this->row())->toArray()['planned_absences']);
    }

    #[Test]
    public function it_emits_the_playable_class_in_its_resource_shape(): void
    {
        $character = Character::factory()->withPlayableClass(PlayableClass::factory()->create(['name' => 'Druid']))->create();

        $playableClass = AttendanceMatrixRowData::fromRow($this->row($character->load('playableClass')))->toArray()['playable_class'];

        $this->assertIsArray($playableClass);
        $this->assertSame('Druid', $playableClass['name']);
        $this->assertArrayHasKey('icon_url', $playableClass);
    }

    #[Test]
    public function it_emits_null_playable_class_for_a_character_without_one(): void
    {
        $character = Character::factory()->create(['playable_class_id' => null]);

        $this->assertNull(AttendanceMatrixRowData::fromRow($this->row($character->load('playableClass')))->toArray()['playable_class']);
    }

    #[Test]
    public function it_runs_no_query_to_build_the_payload(): void
    {
        $gameVersion = $this->makeAttendanceVersion();
        $rank = $this->makeRank($gameVersion);
        $report = $this->makeReport($gameVersion, '2025-01-01 20:00');
        foreach (range(1, 3) as $ignored) {
            $this->attend($report, $this->makeCharacter($gameVersion, $rank, ['playable_class_id' => PlayableClass::factory()->create()->id]));
        }
        $rows = BuildAttendanceMatrix::run($gameVersion)->rows;

        DB::enableQueryLog();
        AttendanceMatrixRowData::collect($rows)->toArray();

        $this->assertSame([], DB::getQueryLog());
    }

    // ==================== helpers ====================

    /**
     * @param  array<int, Presence|null>  $attendance
     * @param  array<int, PlannedAbsence|null>|null  $plannedAbsences
     */
    private function row(?Character $character = null, float $percentage = 0.0, array $attendance = [], ?array $plannedAbsences = null): CharacterAttendanceRowData
    {
        return new CharacterAttendanceRowData(
            character: $character ?? Character::factory()->create()->load('playableClass'),
            firstAttendance: Carbon::parse('2025-01-01 19:00', 'UTC'),
            totalReports: 0,
            reportsAttended: 0,
            percentage: $percentage,
            attendance: $attendance,
            plannedAbsences: $plannedAbsences ?? array_fill(0, count($attendance), null),
        );
    }
}
