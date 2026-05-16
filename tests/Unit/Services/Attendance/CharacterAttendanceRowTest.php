<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use App\Services\Attendance\CharacterAttendanceRowData;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use JsonSerializable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterAttendanceRowTest extends TestCase
{
    use RefreshDatabase;

    protected function makeRow(array $overrides = []): CharacterAttendanceRowData
    {
        $rank = GuildRank::factory()->create();
        $character = isset($overrides['character'])
            ? $overrides['character']
            : Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);

        return new CharacterAttendanceRowData(
            character: $character,
            percentage: $overrides['percentage'] ?? 75.0,
            attendance: $overrides['attendance'] ?? [1, 0, 1],
            plannedAbsences: $overrides['plannedAbsences'] ?? [null, null, null],
            attendanceNames: $overrides['attendanceNames'] ?? null,
        );
    }

    #[Test]
    public function it_exposes_typed_properties(): void
    {
        $row = $this->makeRow();

        $this->assertInstanceOf(Character::class, $row->character);
        $this->assertSame(75.0, $row->percentage);
        $this->assertSame([1, 0, 1], $row->attendance);
        $this->assertSame([null, null, null], $row->plannedAbsences);
        $this->assertNull($row->attendanceNames);
    }

    #[Test]
    public function it_implements_arrayable_and_json_serializable(): void
    {
        $row = $this->makeRow();

        $this->assertInstanceOf(Arrayable::class, $row);
        $this->assertInstanceOf(JsonSerializable::class, $row);
    }

    #[Test]
    public function to_array_flattens_character_into_top_level_keys(): void
    {
        $row = $this->makeRow();

        $array = $row->toArray();

        $this->assertSame($row->character->id, $array['id']);
        $this->assertSame('Thrall', $array['name']);
        $this->assertSame($row->character->rank_id, $array['rank_id']);
        $this->assertArrayHasKey('playable_class', $array);
        $this->assertSame(75.0, $array['percentage']);
        $this->assertSame([1, 0, 1], $array['attendance']);
    }

    #[Test]
    public function to_array_maps_planned_absences_to_ids(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['rank_id' => $rank->id]);
        $absence = PlannedAbsence::factory()->create(['character_id' => $character->id]);

        $row = $this->makeRow([
            'character' => $character,
            'plannedAbsences' => [$absence, null],
        ]);

        $array = $row->toArray();

        $this->assertSame($absence->id, $array['planned_absences'][0]);
        $this->assertNull($array['planned_absences'][1]);
    }

    #[Test]
    public function to_array_omits_attendance_names_when_null(): void
    {
        $row = $this->makeRow(['attendanceNames' => null]);

        $this->assertArrayNotHasKey('attendance_names', $row->toArray());
    }

    #[Test]
    public function to_array_includes_attendance_names_when_provided(): void
    {
        $row = $this->makeRow([
            'attendanceNames' => [['Thrall'], []],
        ]);

        $this->assertSame([['Thrall'], []], $row->toArray()['attendance_names']);
    }

    #[Test]
    public function json_serialize_matches_to_array(): void
    {
        $row = $this->makeRow();

        $this->assertSame($row->toArray(), $row->jsonSerialize());
    }

    #[Test]
    public function to_array_includes_playable_class_relationship(): void
    {
        $playableClass = PlayableClass::factory()->create(['id' => 1, 'name' => 'Warrior']);
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->withPlayableClass($playableClass)->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $character->load('playableClass');

        $row = $this->makeRow(['character' => $character]);
        $array = $row->toArray();

        $this->assertInstanceOf(PlayableClass::class, $array['playable_class']);
        $this->assertSame(1, $array['playable_class']->id);
        $this->assertSame('Warrior', $array['playable_class']->name);
    }

    #[Test]
    public function to_array_includes_null_playable_class_when_not_set(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id, 'playable_class_id' => null]);
        $character->load('playableClass');

        $row = $this->makeRow(['character' => $character]);
        $array = $row->toArray();

        $this->assertNull($array['playable_class']);
    }
}
