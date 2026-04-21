<?php

namespace App\Services\Attendance;

use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\Raids\Report;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DataTable
{
    /**
     * Memoised output of mergeLinkedReports() + sortReportClusters() for the loaded reports.
     *
     * @var Collection<int, ReportClusterData>|null
     */
    private ?Collection $records = null;

    public function __construct(
        private Calculator $calculator,
        private FiltersData $filters
    ) {}

    /**
     * Get the raid column metadata (one entry per merged-linked-reports group), in reverse-chronological order.
     *
     * @return Collection<int, array{id: string, code: string|null, dayOfWeek: string, date: string, zoneName: string|null}>
     */
    public function columns(): Collection
    {
        $timezone = config('app.timezone');

        return $this->records()
            ->map(fn (ReportClusterData $cluster) => [
                'id' => $cluster->id(),
                'code' => $cluster->code(),
                'dayOfWeek' => $cluster->startTime()->copy()->setTimezone($timezone)->format('D'),
                'date' => $cluster->startTime()->copy()->setTimezone($timezone)->format('d/m'),
                'zoneName' => $cluster->zoneName(),
            ])
            ->reverse()
            ->values();
    }

    /**
     * Get per-character attendance rows, sorted alphabetically by character name.
     *
     * Attendance values: null = before the character's first raid, 0 = absent, 1 = present, 2 = late.
     * Percentage excludes raids covered by a planned absence.
     *
     * @return Collection<int, CharacterAttendanceRowData>
     */
    public function rows(): Collection
    {
        $records = $this->records();

        if ($records->isEmpty()) {
            return collect();
        }

        /** @var array<int, array{startTime: Carbon, players: Collection<string, PlayerPresenceData>}> $clusterSnapshots */
        $clusterSnapshots = $records->values()->map(fn (ReportClusterData $cluster) => [
            'startTime' => $cluster->startTime(),
            'players' => $cluster->players(),
        ])->all();

        /** @var array<string, array{characterId: int, firstIndex: int}> $characterInfo */
        $characterInfo = [];

        foreach ($clusterSnapshots as $index => $snapshot) {
            foreach ($snapshot['players'] as $name => $player) {
                if (! isset($characterInfo[$name])) {
                    $characterInfo[$name] = [
                        'characterId' => $player->character->id,
                        'firstIndex' => $index,
                    ];
                }
            }
        }

        $characterIds = array_map(fn (array $info) => $info['characterId'], $characterInfo);

        $characters = Character::whereIn('id', $characterIds)
            ->with(['rank', 'linkedCharacters'])
            ->get()
            ->keyBy('id');

        if ($this->filters->includeLinkedCharacters) {
            $resolvedIds = $this->resolvedRankIds();

            $characters = $characters->filter(
                function (Character $c) use ($resolvedIds) {
                    if (in_array($c->rank_id, $resolvedIds, true)) {
                        return true;
                    }

                    return $c->linkedCharacters->contains(
                        fn (Character $linked) => in_array($linked->rank_id, $resolvedIds, true)
                    );
                }
            );
        }

        $absencesByCharacterId = PlannedAbsence::whereIn('character_id', $characterIds)
            ->get()
            ->groupBy('character_id');

        $reversedSnapshots = array_reverse($clusterSnapshots);
        $totalClusters = count($clusterSnapshots);

        $rows = [];

        foreach ($characterInfo as $name => $info) {
            $character = $characters[$info['characterId']] ?? null;

            if ($character === null) {
                continue;
            }

            $totalReports = 0;
            $reportsAttended = 0;
            $attendance = [];
            $plannedAbsences = [];

            foreach ($reversedSnapshots as $reverseIndex => $snapshot) {
                $originalIndex = $totalClusters - 1 - $reverseIndex;

                $coveringAbsence = $this->calculator->isCoveredByPlannedAbsence(
                    $absencesByCharacterId->get($character->id, collect()),
                    $snapshot['startTime'],
                );

                if ($originalIndex < $info['firstIndex']) {
                    $attendance[] = null;
                    $plannedAbsences[] = null;

                    continue;
                }

                $player = $snapshot['players'][$name] ?? null;
                $presence = $player?->presence;
                $attended = in_array($presence, [1, 2], true);

                $attendance[] = $attended ? $presence : 0;
                $plannedAbsences[] = $coveringAbsence;

                if ($coveringAbsence === null) {
                    $totalReports++;

                    if ($attended) {
                        $reportsAttended++;
                    }
                }
            }

            $percentage = $totalReports > 0 ? round(($reportsAttended / $totalReports) * 100, 2) : 0.0;

            $rows[] = new CharacterAttendanceRowData(
                character: $character,
                percentage: $percentage,
                attendance: $attendance,
                plannedAbsences: $plannedAbsences,
            );
        }

        usort($rows, fn (CharacterAttendanceRowData $a, CharacterAttendanceRowData $b) => strcmp($a->character->name, $b->character->name));

        return collect($rows);
    }

    /**
     * The resolved rank IDs used for the character eager-load filter, exposed so Matrix can reuse them for alt merging.
     *
     * @return array<int, int>
     */
    public function resolvedRankIds(): array
    {
        return ! empty($this->filters->rankIds)
            ? $this->filters->rankIds
            : GuildRank::where('count_attendance', true)->pluck('id')->toArray();
    }

    /**
     * Memoised merged + sorted report clusters for the loaded reports.
     *
     * @return Collection<int, ReportClusterData>
     */
    private function records(): Collection
    {
        if ($this->records !== null) {
            return $this->records;
        }

        return $this->records = $this->calculator->sortReportClusters(
            $this->calculator->mergeLinkedReports($this->reportQuery()->get()),
        );
    }

    /**
     * Build the eager-loaded Report query honouring the Filters state.
     */
    private function reportQuery(): Builder
    {
        $query = Report::query();

        if (! empty($this->filters->guildTagIds)) {
            $query->whereIn('guild_tag_id', $this->filters->guildTagIds);
        } else {
            $query->whereHas('guildTag', fn ($q) => $q->where('count_attendance', true));
        }

        if ($this->filters->zoneIds !== null) {
            $query->whereIn('zone_id', $this->filters->zoneIds);
        }

        if ($this->filters->sinceDate !== null) {
            $query->where('start_time', '>=', $this->filters->sinceDate);
        }

        if ($this->filters->beforeDate !== null) {
            $query->where('start_time', '<', $this->filters->beforeDate);
        }

        if ($this->filters->includeLinkedCharacters) {
            $query->with(['characters', 'linkedReports', 'zone']);
        } else {
            $resolvedRankIds = $this->resolvedRankIds();

            $query->with([
                'characters' => fn ($q) => $q->whereHas('rank', fn ($q2) => $q2->whereIn('id', $resolvedRankIds)),
                'linkedReports',
                'zone',
            ]);
        }

        return $query;
    }
}
