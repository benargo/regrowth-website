<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\ClusterReports;
use App\Data\Attendance\ReportClusterData;
use App\Enums\Presence;
use App\Models\Character;
use App\Models\CharacterReport;
use App\Models\Report;
use App\Models\WarcraftLogs\Zone;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ClusterReportsTest extends TestCase
{
    #[Test]
    public function it_returns_no_clusters_for_no_reports(): void
    {
        $this->assertTrue(ClusterReports::run(collect())->isEmpty());
    }

    #[Test]
    public function it_wraps_a_single_report_in_one_cluster(): void
    {
        $clusters = ClusterReports::run(collect([$this->report('a')]));

        $this->assertCount(1, $clusters);
        $this->assertInstanceOf(ReportClusterData::class, $clusters[0]);
        $this->assertSame('a', $clusters[0]->id);
    }

    #[Test]
    public function it_clusters_a_linked_pair(): void
    {
        [$a, $b] = [$this->report('a'), $this->report('b')];
        $this->link($a, $b);

        $clusters = ClusterReports::run(collect([$a, $b]));

        $this->assertCount(1, $clusters);
        $this->assertSame('a+b', $clusters[0]->id);
    }

    #[Test]
    public function it_keeps_unlinked_reports_in_separate_clusters(): void
    {
        $this->assertCount(2, ClusterReports::run(collect([$this->report('a'), $this->report('b')])));
    }

    #[Test]
    public function it_clusters_three_linked_reports_together(): void
    {
        [$a, $b, $c] = [$this->report('a'), $this->report('b'), $this->report('c')];
        $this->link($a, $b);
        $this->link($b, $c);

        $clusters = ClusterReports::run(collect([$a, $b, $c]));

        $this->assertCount(1, $clusters);
        $this->assertSame('a+b+c', $clusters[0]->id);
    }

    #[Test]
    public function it_clusters_linked_manual_reports(): void
    {
        [$a, $b] = [$this->report('a', code: null), $this->report('b', code: null)];
        $this->link($a, $b);

        $clusters = ClusterReports::run(collect([$a, $b]));

        $this->assertSame('a+b', $clusters[0]->id);
        $this->assertNull($clusters[0]->code);
    }

    #[Test]
    public function it_clusters_a_linked_manual_and_warcraft_logs_report(): void
    {
        [$a, $b] = [$this->report('a', code: null), $this->report('b', code: 'WCL')];
        $this->link($a, $b);

        $this->assertSame('WCL', ClusterReports::run(collect([$a, $b]))[0]->code);
    }

    #[Test]
    public function it_follows_a_chain_of_links_deeper_than_one(): void
    {
        [$a, $b, $c, $d] = [$this->report('a'), $this->report('b'), $this->report('c'), $this->report('d')];
        $this->link($c, $d);
        $this->link($b, $c);
        $this->link($a, $b);

        $this->assertSame('a+b+c+d', ClusterReports::run(collect([$a, $b, $c, $d]))[0]->id);
    }

    #[Test]
    public function it_ignores_links_to_reports_outside_the_input(): void
    {
        [$a, $outside] = [$this->report('a'), $this->report('z')];
        $this->link($a, $outside);

        $clusters = ClusterReports::run(collect([$a]));

        $this->assertCount(1, $clusters);
        $this->assertSame('a', $clusters[0]->id);
    }

    #[Test]
    public function it_orders_clusters_by_start_time(): void
    {
        $clusters = ClusterReports::run(collect([
            $this->report('late', startTime: '2025-01-08 19:00'),
            $this->report('early', startTime: '2025-01-01 19:00'),
        ]));

        $this->assertSame(['early', 'late'], $clusters->map->id->all());
    }

    // ==================== id and code ====================

    #[Test]
    public function merged_id_joins_sorted_report_ids_with_plus(): void
    {
        [$b, $a] = [$this->report('b'), $this->report('a')];
        $this->link($b, $a);

        $this->assertSame('a+b', ClusterReports::run(collect([$b, $a]))[0]->id);
    }

    #[Test]
    public function single_report_code_is_the_report_code(): void
    {
        $this->assertSame('ABC', ClusterReports::run(collect([$this->report('a', code: 'ABC')]))[0]->code);
    }

    #[Test]
    public function single_report_null_code_is_null(): void
    {
        $this->assertNull(ClusterReports::run(collect([$this->report('a', code: null)]))[0]->code);
    }

    #[Test]
    public function merged_code_joins_sorted_non_null_codes_with_plus(): void
    {
        [$a, $b] = [$this->report('a', code: 'BBB'), $this->report('b', code: 'AAA')];
        $this->link($a, $b);

        $this->assertSame('AAA+BBB', ClusterReports::run(collect([$a, $b]))[0]->code);
    }

    // ==================== start time and zone ====================

    #[Test]
    public function start_time_is_the_earliest_report_start_time(): void
    {
        [$a, $b] = [$this->report('a', startTime: '2025-01-01 21:00'), $this->report('b', startTime: '2025-01-01 19:00')];
        $this->link($a, $b);

        $cluster = ClusterReports::run(collect([$a, $b]))[0];

        $this->assertTrue($cluster->startTime->eq(Carbon::parse('2025-01-01 19:00', 'Europe/Paris')));
    }

    #[Test]
    public function zone_name_is_the_zone_of_the_earliest_report(): void
    {
        [$a, $b] = [
            $this->report('a', startTime: '2025-01-01 21:00', zone: $this->zone(2, 'Gruul')),
            $this->report('b', startTime: '2025-01-01 19:00', zone: $this->zone(1, 'Karazhan')),
        ];
        $this->link($a, $b);

        $this->assertSame('Karazhan', ClusterReports::run(collect([$a, $b]))[0]->zoneName);
    }

    #[Test]
    public function zone_name_is_null_when_earliest_report_has_no_zone(): void
    {
        $this->assertNull(ClusterReports::run(collect([$this->report('a', zone: $this->zone(0, 'No zone'))]))[0]->zoneName);
    }

    #[Test]
    public function zone_name_is_null_when_earliest_report_zone_relation_is_null(): void
    {
        $report = $this->report('a')->setRelation('zone', null);

        $this->assertNull(ClusterReports::run(collect([$report]))[0]->zoneName);
    }

    // ==================== players ====================

    #[Test]
    public function players_contains_one_entry_per_character_for_a_single_report(): void
    {
        $players = ClusterReports::run(collect([$this->report('a', presences: [
            [$this->character(1, 'Thrall'), 1],
            [$this->character(2, 'Jaina'), 0],
        ])]))[0]->players;

        $this->assertSame([1, 2], $players->keys()->all());
        $this->assertSame(Presence::Present, $players[1]->presence);
        $this->assertSame(Presence::Absent, $players[2]->presence);
    }

    #[Test]
    public function players_keeps_the_highest_priority_presence(): void
    {
        $this->assertSame(Presence::Present, $this->mergedPresence(0, 1));
    }

    #[Test]
    public function players_prefers_present_over_benched(): void
    {
        $this->assertSame(Presence::Present, $this->mergedPresence(2, 1));
    }

    #[Test]
    public function players_prefers_benched_over_absent(): void
    {
        $this->assertSame(Presence::Benched, $this->mergedPresence(0, 2));
    }

    #[Test]
    public function players_keys_characters_by_id_not_name(): void
    {
        $players = ClusterReports::run(collect([$this->report('a', presences: [
            [$this->character(1, 'Thrall'), 1],
            [$this->character(2, 'Thrall'), 0],
        ])]))[0]->players;

        $this->assertCount(2, $players);
    }

    #[Test]
    public function players_treats_an_unknown_presence_value_as_absent(): void
    {
        $players = ClusterReports::run(collect([$this->report('a', presences: [[$this->character(1, 'Thrall'), 3]])]))[0]->players;

        $this->assertSame(Presence::Absent, $players[1]->presence);
    }

    // ==================== helpers ====================

    /**
     * @param  array<int, array{Character, int}>  $presences
     */
    private function report(string $id, ?string $code = 'CODE', string $startTime = '2025-01-01 19:00', ?Zone $zone = null, array $presences = []): Report
    {
        $report = Report::factory()->make(['id' => $id, 'code' => $code, 'start_time' => Carbon::parse($startTime, 'Europe/Paris')]);

        $report->setRelation('linkedReports', new EloquentCollection);
        $report->setRelation('zone', $zone ?? $this->zone(0, 'No zone'));
        $report->setRelation('characters', new EloquentCollection(array_map(
            fn (array $presence): Character => (clone $presence[0])->setRelation('pivot', new CharacterReport(['presence' => $presence[1]])),
            $presences,
        )));

        return $report;
    }

    private function link(Report $first, Report $second): void
    {
        $first->linkedReports->push($second);
        $second->linkedReports->push($first);
    }

    private function character(int $id, string $name): Character
    {
        return Character::factory()->withoutParents()->make(['id' => $id, 'name' => $name, 'playable_class_id' => null]);
    }

    private function zone(int $id, string $name): Zone
    {
        return (new Zone)->forceFill(['id' => $id, 'name' => $name]);
    }

    /**
     * The merged presence of one character recorded in two linked reports.
     */
    private function mergedPresence(int $firstPresence, int $secondPresence): Presence
    {
        $thrall = $this->character(1, 'Thrall');
        [$a, $b] = [$this->report('a', presences: [[$thrall, $firstPresence]]), $this->report('b', presences: [[$thrall, $secondPresence]])];
        $this->link($a, $b);

        return ClusterReports::run(collect([$a, $b]))[0]->players[1]->presence;
    }
}
