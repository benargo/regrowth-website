<?php

namespace Tests\Unit\Data\Attendance;

use App\Data\Attendance\ReportClusterData;
use App\Enums\Presence;
use App\Models\Character;
use App\Models\CharacterReport;
use App\Models\Report;
use App\Models\WarcraftLogs\Zone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ReportClusterDataTest extends TestCase
{
    #[Test]
    public function it_throws_when_constructed_with_an_empty_collection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ReportClusterData(collect());
    }

    // ==================== id and code ====================

    #[Test]
    public function single_report_id_is_the_report_id(): void
    {
        $this->assertSame('r1', $this->cluster($this->report('r1'))->id());
    }

    #[Test]
    public function merged_id_joins_sorted_report_ids_with_plus(): void
    {
        $this->assertSame('a+b', $this->cluster($this->report('b'), $this->report('a'))->id());
    }

    #[Test]
    public function single_report_code_is_the_report_code(): void
    {
        $this->assertSame('ABC', $this->cluster($this->report('r1', code: 'ABC'))->code());
    }

    #[Test]
    public function null_code_returns_null(): void
    {
        $this->assertNull($this->cluster($this->report('r1', code: null))->code());
    }

    #[Test]
    public function merged_code_joins_sorted_non_null_codes_with_plus(): void
    {
        $this->assertSame('AAA+BBB', $this->cluster($this->report('r1', code: 'BBB'), $this->report('r2', code: 'AAA'))->code());
    }

    #[Test]
    public function merged_code_filters_nulls_out(): void
    {
        $this->assertSame('AAA', $this->cluster($this->report('r1', code: null), $this->report('r2', code: 'AAA'))->code());
    }

    #[Test]
    public function merged_code_is_null_when_all_reports_are_manual(): void
    {
        $this->assertNull($this->cluster($this->report('r1', code: null), $this->report('r2', code: null))->code());
    }

    // ==================== start time and zone ====================

    #[Test]
    public function start_time_is_the_earliest_report_start_time(): void
    {
        $cluster = $this->cluster(
            $this->report('r1', startTime: '2025-01-01 21:00'),
            $this->report('r2', startTime: '2025-01-01 19:00'),
        );

        $this->assertTrue($cluster->startTime()->eq(Carbon::parse('2025-01-01 19:00', 'Europe/Paris')));
    }

    #[Test]
    public function zone_name_is_the_zone_of_the_earliest_report(): void
    {
        $cluster = $this->cluster(
            $this->report('r1', startTime: '2025-01-01 21:00', zone: $this->zone(2, 'Gruul')),
            $this->report('r2', startTime: '2025-01-01 19:00', zone: $this->zone(1, 'Karazhan')),
        );

        $this->assertSame('Karazhan', $cluster->zoneName());
    }

    #[Test]
    public function zone_name_is_null_when_earliest_report_has_no_zone(): void
    {
        $this->assertNull($this->cluster($this->report('r1', zone: $this->zone(0, 'No zone')))->zoneName());
    }

    #[Test]
    public function zone_name_is_null_when_earliest_report_zone_relation_is_null(): void
    {
        $report = $this->report('r1')->setRelation('zone', null);

        $this->assertNull($this->cluster($report)->zoneName());
    }

    // ==================== isMerged ====================

    #[Test]
    public function a_single_report_cluster_is_not_merged(): void
    {
        $this->assertFalse($this->cluster($this->report('r1'))->isMerged());
    }

    #[Test]
    public function a_multi_report_cluster_is_merged(): void
    {
        $this->assertTrue($this->cluster($this->report('r1'), $this->report('r2'))->isMerged());
    }

    // ==================== players ====================

    #[Test]
    public function players_contains_one_entry_per_character_for_a_single_report(): void
    {
        $thrall = $this->character(1, 'Thrall');
        $jaina = $this->character(2, 'Jaina');

        $players = $this->cluster($this->report('r1', presences: [[$thrall, 1], [$jaina, 0]]))->players();

        $this->assertSame([1, 2], $players->keys()->all());
        $this->assertSame(Presence::Present, $players[1]->presence);
        $this->assertSame(Presence::Absent, $players[2]->presence);
    }

    #[Test]
    public function players_keeps_the_highest_priority_presence(): void
    {
        $thrall = $this->character(1, 'Thrall');

        $players = $this->cluster(
            $this->report('r1', presences: [[$thrall, 0]]),
            $this->report('r2', presences: [[$thrall, 1]]),
        )->players();

        $this->assertSame(Presence::Present, $players[1]->presence);
    }

    #[Test]
    public function players_prefers_present_over_benched(): void
    {
        $thrall = $this->character(1, 'Thrall');

        $players = $this->cluster(
            $this->report('r1', presences: [[$thrall, 2]]),
            $this->report('r2', presences: [[$thrall, 1]]),
        )->players();

        $this->assertSame(Presence::Present, $players[1]->presence);
    }

    #[Test]
    public function players_prefers_benched_over_absent(): void
    {
        $thrall = $this->character(1, 'Thrall');

        $players = $this->cluster(
            $this->report('r1', presences: [[$thrall, 0]]),
            $this->report('r2', presences: [[$thrall, 2]]),
        )->players();

        $this->assertSame(Presence::Benched, $players[1]->presence);
    }

    #[Test]
    public function players_keys_characters_by_id_not_name(): void
    {
        $players = $this->cluster($this->report('r1', presences: [
            [$this->character(1, 'Thrall'), 1],
            [$this->character(2, 'Thrall'), 0],
        ]))->players();

        $this->assertCount(2, $players);
    }

    #[Test]
    public function players_treats_an_unknown_presence_value_as_absent(): void
    {
        $players = $this->cluster($this->report('r1', presences: [[$this->character(1, 'Thrall'), 3]]))->players();

        $this->assertSame(Presence::Absent, $players[1]->presence);
    }

    // ==================== serialisation ====================

    #[Test]
    public function to_array_emits_the_expected_shape(): void
    {
        $array = $this->cluster($this->report('r1', code: 'ABC', startTime: '2025-01-01 19:00', zone: $this->zone(1, 'Karazhan'), presences: [[$this->character(1, 'Thrall'), 1]]))->toArray();

        $this->assertSame('r1', $array['id']);
        $this->assertSame('ABC', $array['code']);
        $this->assertSame('2025-01-01T18:00:00.000000Z', $array['startTime']);
        $this->assertSame('Karazhan', $array['zoneName']);
        $this->assertSame(Presence::Present, $array['players'][0]['presence']);
    }

    #[Test]
    public function json_serialize_matches_to_array(): void
    {
        $cluster = $this->cluster($this->report('r1', presences: [[$this->character(1, 'Thrall'), 1]]));

        $this->assertSame($cluster->toArray(), $cluster->jsonSerialize());
    }

    // ==================== helpers ====================

    private function cluster(Report ...$reports): ReportClusterData
    {
        return new ReportClusterData(collect($reports));
    }

    /**
     * @param  array<int, array{Character, int}>  $presences
     */
    private function report(string $id, ?string $code = 'CODE', string $startTime = '2025-01-01 19:00', ?Zone $zone = null, array $presences = []): Report
    {
        $report = Report::factory()->make(['id' => $id, 'code' => $code, 'start_time' => Carbon::parse($startTime, 'Europe/Paris')]);

        $report->setRelation('zone', $zone ?? $this->zone(0, 'No zone'));
        $report->setRelation('characters', new EloquentCollection(array_map(
            fn (array $presence): Character => (clone $presence[0])->setRelation('pivot', new CharacterReport(['presence' => $presence[1]])),
            $presences,
        )));

        return $report;
    }

    private function character(int $id, string $name): Character
    {
        return Character::factory()->withoutParents()->make(['id' => $id, 'name' => $name, 'playable_class_id' => null]);
    }

    private function zone(int $id, string $name): Zone
    {
        return (new Zone)->forceFill(['id' => $id, 'name' => $name]);
    }
}
