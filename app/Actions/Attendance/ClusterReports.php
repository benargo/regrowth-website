<?php

namespace App\Actions\Attendance;

use App\Data\Attendance\ReportClusterData;
use App\Models\Report;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Groups reports joined through their report links into raid clusters, ordered by start time.
 */
class ClusterReports
{
    use AsAction;

    /**
     * @param  Collection<int, Report>  $reports  With `linkedReports` loaded. Links to reports outside this collection are ignored.
     * @return Collection<int, ReportClusterData>
     */
    public function handle(Collection $reports): Collection
    {
        if ($reports->isEmpty()) {
            return collect();
        }

        $ids = $reports->pluck('id')->all();
        $parent = array_combine($ids, $ids);

        foreach ($reports as $report) {
            foreach ($report->linkedReports as $linked) {
                if (! isset($parent[$linked->id])) {
                    continue;
                }

                $rootA = $this->find($parent, $report->id);
                $rootB = $this->find($parent, $linked->id);

                if ($rootA !== $rootB) {
                    $parent[$rootB] = $rootA;
                }
            }
        }

        return $reports
            ->groupBy(fn (Report $report): string => $this->find($parent, $report->id))
            ->map(fn (Collection $group): ReportClusterData => new ReportClusterData($group->values()))
            ->sortBy(fn (ReportClusterData $cluster) => $cluster->startTime())
            ->values();
    }

    /**
     * Find the root of the given report ID, compressing the path to the root along the way.
     *
     * @param  array<string, string>  $parent
     */
    private function find(array &$parent, string $id): string
    {
        $root = $id;

        while ($parent[$root] !== $root) {
            $root = $parent[$root];
        }

        while ($parent[$id] !== $root) {
            $next = $parent[$id];
            $parent[$id] = $root;
            $id = $next;
        }

        return $root;
    }
}
