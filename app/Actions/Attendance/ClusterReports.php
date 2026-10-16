<?php

namespace App\Actions\Attendance;

use App\Data\Attendance\PlayerPresenceData;
use App\Data\Attendance\ReportClusterData;
use App\Enums\Presence;
use App\Models\Report;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Groups reports joined through their report links into raid clusters, ordered by start time.
 */
class ClusterReports
{
    use AsAction;

    /**
     * @param  Collection<int, Report>  $reports  With `linkedReports`, `characters` (with `pivot.presence`) and `zone` loaded. Links to reports outside this collection are ignored.
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
            ->map(fn (Collection $group): ReportClusterData => $this->cluster($group))
            ->sortBy(fn (ReportClusterData $cluster): CarbonInterface => $cluster->startTime)
            ->values();
    }

    /**
     * Combine a group of linked reports into one raid. The earliest report gives the
     * start time and zone; a zone with ID 0 counts as no zone.
     *
     * @param  Collection<int, Report>  $reports
     */
    private function cluster(Collection $reports): ReportClusterData
    {
        $earliest = $reports->sortBy('start_time')->first();
        $codes = $reports->pluck('code')->filter()->sort()->values();

        return new ReportClusterData(
            id: $reports->pluck('id')->sort()->implode('+'),
            code: $codes->isEmpty() ? null : $codes->implode('+'),
            startTime: $earliest->start_time,
            zoneName: ($earliest->zone === null || $earliest->zone->id === 0) ? null : $earliest->zone->name,
            players: $this->players($reports),
        );
    }

    /**
     * Merge each character's presence across the reports, keyed by character ID. The
     * presence with the highest priority wins; an unknown stored value counts as absent.
     *
     * @param  Collection<int, Report>  $reports
     * @return Collection<int, PlayerPresenceData>
     */
    private function players(Collection $reports): Collection
    {
        $merged = [];

        foreach ($reports as $report) {
            foreach ($report->characters as $character) {
                $presence = Presence::tryFrom((int) $character->pivot->presence) ?? Presence::Absent;

                if (! isset($merged[$character->id]) || $presence->priority() > $merged[$character->id]->presence->priority()) {
                    $merged[$character->id] = new PlayerPresenceData($character, $presence);
                }
            }
        }

        return collect($merged);
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
