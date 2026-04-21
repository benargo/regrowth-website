<?php

namespace Tests\Unit\Services\Attendance;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Services\Attendance\Calculator;
use App\Services\Attendance\CharacterAttendanceRowData;
use App\Services\Attendance\DataTable;
use App\Services\Attendance\Graphs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GraphsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Build a Graphs instance whose DataTable returns the given rows.
     *
     * @param  array<int, CharacterAttendanceRowData>  $rows
     */
    private function makeGraphs(array $rows): Graphs
    {
        $table = Mockery::mock(DataTable::class);
        $table->shouldReceive('rows')->andReturn(collect($rows));

        return new Graphs(new Calculator, $table);
    }

    private function makeCharacter(string $name = 'Thrall'): Character
    {
        $rank = GuildRank::factory()->create();

        return Character::factory()->create(['name' => $name, 'rank_id' => $rank->id]);
    }

    #[Test]
    public function it_builds_one_scatter_point_per_row(): void
    {
        $rowA = new CharacterAttendanceRowData(
            character: $this->makeCharacter('Aerith'),
            percentage: 80.0,
            attendance: [1, 1, 1, 1, 0],
            plannedAbsences: [null, null, null, null, null],
        );
        $rowB = new CharacterAttendanceRowData(
            character: $this->makeCharacter('Barret'),
            percentage: 50.0,
            attendance: [1, 0, 1, 0],
            plannedAbsences: [null, null, null, null],
        );

        $collection = $this->makeGraphs([$rowA, $rowB])->scatterPoints();
        $data = $collection->resolve();

        $this->assertCount(2, $data);
        $this->assertSame('Aerith', $data[0]['name']);
        $this->assertSame('Barret', $data[1]['name']);
        $this->assertSame(80.0, $data[0]['percentage']);
        $this->assertSame(50.0, $data[1]['percentage']);
    }

    #[Test]
    public function it_excludes_pre_start_raids_from_raids_total(): void
    {
        $row = new CharacterAttendanceRowData(
            character: $this->makeCharacter(),
            percentage: 100.0,
            attendance: [null, null, 1, 1, 1],
            plannedAbsences: [null, null, null, null, null],
        );

        $point = $this->makeGraphs([$row])->scatterPoints()->resolve()[0];

        $this->assertSame(3, $point['raidsTotal']);
        $this->assertSame(3, $point['raidsAttended']);
    }

    #[Test]
    public function it_distinguishes_attended_benched_and_other_absences(): void
    {
        $row = new CharacterAttendanceRowData(
            character: $this->makeCharacter(),
            percentage: 60.0,
            attendance: [1, 1, 1, 2, 2, 0, 0, 0, 0, 0],
            plannedAbsences: array_fill(0, 10, null),
        );

        $point = $this->makeGraphs([$row])->scatterPoints()->resolve()[0];

        $this->assertSame(10, $point['raidsTotal']);
        $this->assertSame(3, $point['raidsAttended']);
        $this->assertSame(2, $point['benched']);
        $this->assertSame(5, $point['otherAbsences']);
    }

    #[Test]
    public function it_counts_planned_absences_from_row_planned_absences(): void
    {
        $character = $this->makeCharacter();
        $absence = PlannedAbsence::factory()->create(['character_id' => $character->id]);

        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 0.0,
            attendance: [0, 0, 0, 0],
            plannedAbsences: [$absence, $absence, null, null],
        );

        $point = $this->makeGraphs([$row])->scatterPoints()->resolve()[0];

        $this->assertSame(2, $point['plannedAbsences']);
    }

    #[Test]
    public function it_exposes_character_id_and_playable_class(): void
    {
        $character = $this->makeCharacter('Cloud');

        $row = new CharacterAttendanceRowData(
            character: $character,
            percentage: 75.0,
            attendance: [1, 0, 1, 0],
            plannedAbsences: [null, null, null, null],
        );

        $point = $this->makeGraphs([$row])->scatterPoints()->resolve()[0];

        $this->assertSame($character->id, $point['id']);
        $this->assertSame('Cloud', $point['name']);
        $this->assertSame($character->playable_class, $point['playable_class']);
    }

    #[Test]
    public function it_returns_empty_collection_when_no_rows(): void
    {
        $collection = $this->makeGraphs([])->scatterPoints();

        $this->assertCount(0, $collection->resolve());
    }
}
