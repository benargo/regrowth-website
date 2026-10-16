<?php

namespace Tests\Feature\Actions\Attendance;

use App\Actions\Attendance\ClusterReports;
use App\Data\Attendance\ReportClusterData;
use App\Models\Report;
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
        $this->assertFalse($clusters[0]->isMerged());
    }

    #[Test]
    public function it_clusters_a_linked_pair(): void
    {
        [$a, $b] = [$this->report('a'), $this->report('b')];
        $this->link($a, $b);

        $clusters = ClusterReports::run(collect([$a, $b]));

        $this->assertCount(1, $clusters);
        $this->assertSame('a+b', $clusters[0]->id());
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
        $this->assertSame('a+b+c', $clusters[0]->id());
    }

    #[Test]
    public function it_clusters_linked_manual_reports(): void
    {
        [$a, $b] = [$this->report('a', code: null), $this->report('b', code: null)];
        $this->link($a, $b);

        $clusters = ClusterReports::run(collect([$a, $b]));

        $this->assertSame('a+b', $clusters[0]->id());
        $this->assertNull($clusters[0]->code());
    }

    #[Test]
    public function it_clusters_a_linked_manual_and_warcraft_logs_report(): void
    {
        [$a, $b] = [$this->report('a', code: null), $this->report('b', code: 'WCL')];
        $this->link($a, $b);

        $this->assertSame('WCL', ClusterReports::run(collect([$a, $b]))[0]->code());
    }

    #[Test]
    public function it_follows_a_chain_of_links_deeper_than_one(): void
    {
        [$a, $b, $c, $d] = [$this->report('a'), $this->report('b'), $this->report('c'), $this->report('d')];
        $this->link($c, $d);
        $this->link($b, $c);
        $this->link($a, $b);

        $this->assertSame('a+b+c+d', ClusterReports::run(collect([$a, $b, $c, $d]))[0]->id());
    }

    #[Test]
    public function it_ignores_links_to_reports_outside_the_input(): void
    {
        [$a, $outside] = [$this->report('a'), $this->report('z')];
        $this->link($a, $outside);

        $clusters = ClusterReports::run(collect([$a]));

        $this->assertCount(1, $clusters);
        $this->assertSame('a', $clusters[0]->id());
    }

    #[Test]
    public function it_orders_clusters_by_start_time(): void
    {
        $clusters = ClusterReports::run(collect([
            $this->report('late', startTime: '2025-01-08 19:00'),
            $this->report('early', startTime: '2025-01-01 19:00'),
        ]));

        $this->assertSame(['early', 'late'], $clusters->map->id()->all());
    }

    // ==================== helpers ====================

    private function report(string $id, ?string $code = 'CODE', string $startTime = '2025-01-01 19:00'): Report
    {
        return Report::factory()->make(['id' => $id, 'code' => $code, 'start_time' => Carbon::parse($startTime, 'UTC')])
            ->setRelation('linkedReports', new EloquentCollection);
    }

    private function link(Report $first, Report $second): void
    {
        $first->linkedReports->push($second);
        $second->linkedReports->push($first);
    }
}
