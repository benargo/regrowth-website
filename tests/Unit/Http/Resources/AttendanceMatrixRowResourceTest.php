<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\AttendanceMatrixRowResource;
use App\Models\Character;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use App\Services\Attendance\CharacterAttendanceRowData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AttendanceMatrixRowResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_base_keys(): void
    {
        $character = Character::factory()->create();
        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 0.75,
            attendance: [],
            plannedAbsences: [],
        );

        $array = (new AttendanceMatrixRowResource($row))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('rank_id', $array);
        $this->assertArrayHasKey('percentage', $array);
        $this->assertArrayHasKey('attendance', $array);
        $this->assertArrayHasKey('planned_absences', $array);
    }

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $character = Character::factory()->create(['name' => 'Arthas']);
        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 0.85,
            attendance: [1, 0, 1],
            plannedAbsences: [],
        );

        $array = (new AttendanceMatrixRowResource($row))->toArray(new Request);

        $this->assertSame($character->id, $array['id']);
        $this->assertSame('Arthas', $array['name']);
        $this->assertSame($character->rank_id, $array['rank_id']);
        $this->assertSame(0.85, $array['percentage']);
        $this->assertSame([1, 0, 1], $array['attendance']);
    }

    #[Test]
    public function it_maps_planned_absences_to_ids(): void
    {
        $character = Character::factory()->create();
        $absences = PlannedAbsence::factory()->count(2)->create(['character_id' => $character->id]);

        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 1.0,
            attendance: [],
            plannedAbsences: [$absences[0], null, $absences[1]],
        );

        $array = (new AttendanceMatrixRowResource($row))->toArray(new Request);

        $this->assertSame([$absences[0]->id, null, $absences[1]->id], $array['planned_absences']);
    }

    #[Test]
    public function it_returns_empty_planned_absences_when_none(): void
    {
        $character = Character::factory()->create();
        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 1.0,
            attendance: [],
            plannedAbsences: [],
        );

        $array = (new AttendanceMatrixRowResource($row))->toArray(new Request);

        $this->assertSame([], $array['planned_absences']);
    }

    #[Test]
    public function it_excludes_playable_class_when_relation_not_loaded(): void
    {
        $character = Character::factory()->create();
        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 1.0,
            attendance: [],
            plannedAbsences: [],
        );

        $array = (new AttendanceMatrixRowResource($row))->toArray(new Request);

        $this->assertArrayNotHasKey('playable_class', $array);
    }

    #[Test]
    public function it_includes_playable_class_when_relation_is_loaded(): void
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

        $array = (new AttendanceMatrixRowResource($row))->toArray(new Request);

        $this->assertArrayHasKey('playable_class', $array);
        $this->assertIsArray($array['playable_class']);
        $this->assertSame($playableClass->id, $array['playable_class']['id']);
        $this->assertSame('Druid', $array['playable_class']['name']);
        $this->assertArrayHasKey('icon_url', $array['playable_class']);
    }

    #[Test]
    public function it_includes_null_playable_class_when_relation_loaded_but_null(): void
    {
        $character = Character::factory()->create(['playable_class_id' => null]);
        $character->load('playableClass');

        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 1.0,
            attendance: [],
            plannedAbsences: [],
        );

        $array = (new AttendanceMatrixRowResource($row))->toArray(new Request);

        $this->assertArrayHasKey('playable_class', $array);
        $this->assertNull($array['playable_class']);
    }
}
