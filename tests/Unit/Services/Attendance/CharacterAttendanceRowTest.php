<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use App\Services\Attendance\CharacterAttendanceRowData;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    public function it_stores_planned_absences_as_models(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['rank_id' => $rank->id]);
        $absence = PlannedAbsence::factory()->create(['character_id' => $character->id]);

        $row = $this->makeRow([
            'character' => $character,
            'plannedAbsences' => [$absence, null],
        ]);

        $this->assertInstanceOf(PlannedAbsence::class, $row->plannedAbsences[0]);
        $this->assertSame($absence->id, $row->plannedAbsences[0]->id);
        $this->assertNull($row->plannedAbsences[1]);
    }

    #[Test]
    public function it_stores_attendance_names_when_provided(): void
    {
        $row = $this->makeRow(['attendanceNames' => [['Thrall'], []]]);

        $this->assertSame([['Thrall'], []], $row->attendanceNames);
    }

    #[Test]
    public function it_stores_playable_class_relationship_on_character(): void
    {
        $playableClass = PlayableClass::factory()->create(['id' => 1, 'name' => 'Warrior']);
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->withPlayableClass($playableClass)->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $character->load('playableClass');

        $row = $this->makeRow(['character' => $character]);

        $this->assertInstanceOf(PlayableClass::class, $row->character->playableClass);
        $this->assertSame(1, $row->character->playableClass->id);
        $this->assertSame('Warrior', $row->character->playableClass->name);
    }

    #[Test]
    public function it_stores_null_playable_class_when_not_set(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->create(['name' => 'Thrall', 'rank_id' => $rank->id, 'playable_class_id' => null]);
        $character->load('playableClass');

        $row = $this->makeRow(['character' => $character]);

        $this->assertNull($row->character->playableClass);
    }
}
