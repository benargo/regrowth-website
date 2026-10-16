<?php

namespace App\Actions\Attendance;

use App\Data\Attendance\AttendanceMatrixData;
use App\Data\Attendance\CharacterAttendanceRowData;
use App\Data\Attendance\PlayerPresenceData;
use App\Data\Attendance\ReportClusterData;
use App\Enums\Presence;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\PlannedAbsence;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds a game version's attendance matrix: one column per raid and one
 * row per character, counted from each character's first raid.
 */
class BuildAttendanceMatrix
{
    use AsAction;

    /**
     * @param  array<int, int>|null  $rankIds  Null or empty for the version's counting ranks.
     * @param  array<int, int>|null  $zoneIds  Null for every zone; an empty list for none.
     * @param  array<int, int>|null  $guildTagIds  Null or empty for the tags that count attendance.
     */
    public function handle(
        GameVersion $gameVersion,
        ?array $rankIds = null,
        ?array $zoneIds = null,
        ?array $guildTagIds = null,
        ?CarbonInterface $since = null,
        ?CarbonInterface $before = null,
        bool $combineLinkedCharacters = false,
    ): AttendanceMatrixData {
        $resolvedRankIds = $rankIds ?: $gameVersion->guildRanks()->countsAttendance()->pluck('id')->all();

        $clusters = ClusterReports::run(LoadAttendanceReports::run(
            $gameVersion,
            $resolvedRankIds,
            $zoneIds,
            $guildTagIds,
            $since,
            $before,
            withAllCharacters: $combineLinkedCharacters,
        ));

        $rows = $this->rows($clusters, $resolvedRankIds, $combineLinkedCharacters);

        if ($combineLinkedCharacters && $rows->isNotEmpty()) {
            $rows = CombineLinkedCharacters::run($rows, $resolvedRankIds, $clusters->count());
        }

        return new AttendanceMatrixData(columns: $this->columns($clusters), rows: $rows);
    }

    /**
     * @param  Collection<int, ReportClusterData>  $clusters  Oldest first.
     * @return Collection<int, array{id: string, code: string|null, dayOfWeek: string, date: string, zoneName: string|null}>
     */
    private function columns(Collection $clusters): Collection
    {
        return $clusters
            ->map(fn (ReportClusterData $cluster): array => [
                'id' => $cluster->id(),
                'code' => $cluster->code(),
                'dayOfWeek' => $cluster->startTime()->format('D'),
                'date' => $cluster->startTime()->format('d/m'),
                'zoneName' => $cluster->zoneName(),
            ])
            ->reverse()
            ->values();
    }

    /**
     * @param  Collection<int, ReportClusterData>  $clusters  Oldest first.
     * @param  array<int, int>  $rankIds
     * @return Collection<int, CharacterAttendanceRowData>
     */
    private function rows(Collection $clusters, array $rankIds, bool $includeLinkedCharacters): Collection
    {
        if ($clusters->isEmpty()) {
            return collect();
        }

        /** @var array<int, array{startTime: CarbonInterface, players: Collection<int, PlayerPresenceData>}> $snapshots */
        $snapshots = $clusters
            ->map(fn (ReportClusterData $cluster): array => ['startTime' => $cluster->startTime(), 'players' => $cluster->players()])
            ->all();

        $firstIndexes = [];

        foreach ($snapshots as $index => $snapshot) {
            foreach ($snapshot['players']->keys() as $characterId) {
                $firstIndexes[$characterId] ??= $index;
            }
        }

        $characters = Character::whereIn('id', array_keys($firstIndexes))
            ->with(['rank', 'linkedCharacters', 'playableClass.media'])
            ->get()
            ->when($includeLinkedCharacters, fn (EloquentCollection $characters) => $characters->filter(
                fn (Character $character): bool => in_array($character->rank_id, $rankIds, true)
                    || $character->linkedCharacters->contains(fn (Character $linked): bool => in_array($linked->rank_id, $rankIds, true)),
            ));

        $absencesByCharacterId = PlannedAbsence::whereIn('character_id', $characters->modelKeys())->get()->groupBy('character_id');

        return $characters
            ->map(fn (Character $character): CharacterAttendanceRowData => $this->row(
                $character,
                $snapshots,
                $firstIndexes[$character->id],
                $absencesByCharacterId->get($character->id, collect()),
            ))
            ->sortBy(fn (CharacterAttendanceRowData $row): string => $row->character->name, SORT_STRING)
            ->values();
    }

    /**
     * @param  array<int, array{startTime: CarbonInterface, players: Collection<int, PlayerPresenceData>}>  $snapshots  Oldest first.
     * @param  Collection<int, PlannedAbsence>  $absences
     */
    private function row(Character $character, array $snapshots, int $firstIndex, Collection $absences): CharacterAttendanceRowData
    {
        $attendance = [];
        $plannedAbsences = [];
        $totalReports = 0;
        $reportsAttended = 0;

        foreach (array_reverse($snapshots, true) as $index => $snapshot) {
            if ($index < $firstIndex) {
                $attendance[] = null;
                $plannedAbsences[] = null;

                continue;
            }

            $presence = $snapshot['players']->get($character->id)?->presence;
            $isAttended = $presence?->isAttended() ?? false;
            $absence = $this->absenceCovering($absences, $snapshot['startTime']);

            $attendance[] = $isAttended ? $presence : Presence::Absent;
            $plannedAbsences[] = $absence;

            if ($absence === null) {
                $totalReports++;

                if ($isAttended) {
                    $reportsAttended++;
                }
            }
        }

        return new CharacterAttendanceRowData(
            character: $character,
            firstAttendance: $snapshots[$firstIndex]['startTime'],
            totalReports: $totalReports,
            reportsAttended: $reportsAttended,
            percentage: $totalReports > 0 ? round($reportsAttended / $totalReports * 100, 2) : 0.0,
            attendance: $attendance,
            plannedAbsences: $plannedAbsences,
        );
    }

    /**
     * Find the planned absence whose dates include the raid's date. An absence
     * without an end date includes every date from its start onwards.
     *
     * @param  Collection<int, PlannedAbsence>  $absences
     */
    private function absenceCovering(Collection $absences, CarbonInterface $startTime): ?PlannedAbsence
    {
        $raidDate = $startTime->toDateString();

        return $absences->first(fn (PlannedAbsence $absence): bool => $absence->start_date->toDateString() <= $raidDate
            && ($absence->end_date === null || $raidDate <= $absence->end_date->toDateString()));
    }
}
