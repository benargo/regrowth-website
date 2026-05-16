<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\AttendanceScatterPointResource;
use App\Models\Character;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use App\Services\Attendance\CharacterAttendanceRowData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceScatterPointResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $character = Character::factory()->create();
        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 0.75,
            attendance: [],
            plannedAbsences: [],
        );

        $array = (new AttendanceScatterPointResource($row))->toArray(new Request);

        $this->assertArrayHasKey('character', $array);
        $this->assertArrayHasKey('percentage', $array);
        $this->assertArrayHasKey('raidsTotal', $array);
        $this->assertArrayHasKey('raidsAttended', $array);
        $this->assertArrayHasKey('benched', $array);
        $this->assertArrayHasKey('plannedAbsences', $array);
        $this->assertArrayHasKey('otherAbsences', $array);
    }

    #[Test]
    public function it_returns_correct_percentage(): void
    {
        $character = Character::factory()->create();
        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 0.8,
            attendance: [],
            plannedAbsences: [],
        );

        $array = (new AttendanceScatterPointResource($row))->toArray(new Request);

        $this->assertSame(0.8, $array['percentage']);
    }

    #[Test]
    public function it_counts_raids_total_excluding_nulls(): void
    {
        $character = Character::factory()->create();
        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 1.0,
            attendance: [1, 0, 2, null, 1],
            plannedAbsences: [],
        );

        $array = (new AttendanceScatterPointResource($row))->toArray(new Request);

        $this->assertSame(4, $array['raidsTotal']);
    }

    #[Test]
    public function it_counts_raids_attended(): void
    {
        $character = Character::factory()->create();
        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 1.0,
            attendance: [1, 1, 0, 2, null, 1],
            plannedAbsences: [],
        );

        $array = (new AttendanceScatterPointResource($row))->toArray(new Request);

        $this->assertSame(3, $array['raidsAttended']);
    }

    #[Test]
    public function it_counts_benched(): void
    {
        $character = Character::factory()->create();
        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 1.0,
            attendance: [1, 2, 2, 0, null],
            plannedAbsences: [],
        );

        $array = (new AttendanceScatterPointResource($row))->toArray(new Request);

        $this->assertSame(2, $array['benched']);
    }

    #[Test]
    public function it_counts_other_absences(): void
    {
        $character = Character::factory()->create();
        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 1.0,
            attendance: [0, 1, 0, 2, null],
            plannedAbsences: [],
        );

        $array = (new AttendanceScatterPointResource($row))->toArray(new Request);

        $this->assertSame(2, $array['otherAbsences']);
    }

    #[Test]
    public function it_counts_planned_absences_excluding_nulls(): void
    {
        $character = Character::factory()->create();
        $absences = PlannedAbsence::factory()->count(2)->create(['character_id' => $character->id]);

        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 1.0,
            attendance: [],
            plannedAbsences: [$absences[0], null, $absences[1], null],
        );

        $array = (new AttendanceScatterPointResource($row))->toArray(new Request);

        $this->assertSame(2, $array['plannedAbsences']);
    }

    #[Test]
    public function it_returns_character_as_array(): void
    {
        $character = Character::factory()->create();
        $character->load('playableClass');
        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 1.0,
            attendance: [],
            plannedAbsences: [],
        );

        $array = (new AttendanceScatterPointResource($row))->toArray(new Request);

        $this->assertIsArray($array['character']);
        $this->assertArrayHasKey('id', $array['character']);
        $this->assertArrayHasKey('name', $array['character']);
        $this->assertSame($character->id, $array['character']['id']);
        $this->assertSame($character->name, $array['character']['name']);
    }

    #[Test]
    public function it_includes_playable_class_in_character_when_loaded(): void
    {
        $playableClass = PlayableClass::factory()->create(['name' => 'Druid']);
        $character = Character::factory()->withPlayableClass($playableClass)->create();
        $character->load('playableClass');

        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 1.0,
            attendance: [],
            plannedAbsences: [],
        );

        $array = (new AttendanceScatterPointResource($row))->toArray(new Request);

        $this->assertIsArray($array['character']['playable_class']);
        $this->assertSame($playableClass->id, $array['character']['playable_class']['id']);
        $this->assertSame('Druid', $array['character']['playable_class']['name']);
        $this->assertArrayHasKey('icon_url', $array['character']['playable_class']);
    }

    #[Test]
    public function it_returns_zero_counts_for_empty_attendance(): void
    {
        $character = Character::factory()->create();
        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 0.0,
            attendance: [],
            plannedAbsences: [],
        );

        $array = (new AttendanceScatterPointResource($row))->toArray(new Request);

        $this->assertSame(0, $array['raidsTotal']);
        $this->assertSame(0, $array['raidsAttended']);
        $this->assertSame(0, $array['benched']);
        $this->assertSame(0, $array['otherAbsences']);
        $this->assertSame(0, $array['plannedAbsences']);
    }
}
