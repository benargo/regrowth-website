<?php

namespace App\Actions\Attendance;

use App\Data\Attendance\AttendanceMatrixData;
use App\Data\Attendance\CharacterAttendanceData;
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

        $rows->each(fn (CharacterAttendanceData $row): Character => $row->character->unsetRelation('linkedCharacters')->unsetRelation('pivot'));

        return new AttendanceMatrixData(columns: $clusters->reverse()->values(), rows: $rows);
    }

    /**
     * @param  Collection<int, ReportClusterData>  $clusters  Oldest first.
     * @param  array<int, int>  $rankIds
     * @return Collection<int, CharacterAttendanceData>
     */
    private function rows(Collection $clusters, array $rankIds, bool $includeLinkedCharacters): Collection
    {
        if ($clusters->isEmpty()) {
            return collect();
        }

        $clusters = $clusters->values();

        $characters = $clusters->pluck('players.*.character')
            ->flatten(1)
            ->keyBy('id')
            ->pipeInto(EloquentCollection::class)
            ->load(['linkedCharacters', 'playableClass.media', 'rank'])
            ->when($includeLinkedCharacters, fn (EloquentCollection $characters) => $characters->filter(
                fn (Character $character): bool => in_array($character->rank_id, $rankIds, true)
                    || $character->linkedCharacters->contains(fn (Character $linked): bool => in_array($linked->rank_id, $rankIds, true)),
            ));

        $absencesByCharacterId = PlannedAbsence::whereIn('character_id', $characters->modelKeys())->get()->groupBy('character_id');

        return $characters
            ->map(fn (Character $character): CharacterAttendanceData => $this->row(
                $character,
                $clusters,
                $clusters->search(fn (ReportClusterData $cluster): bool => $cluster->players->has($character->id)),
                $absencesByCharacterId->get($character->id, collect()),
            ))
            ->sortBy(fn (CharacterAttendanceData $row): string => $row->character->name, SORT_STRING)
            ->values();
    }

    /**
     * @param  Collection<int, ReportClusterData>  $clusters  Oldest first, keyed from zero.
     * @param  Collection<int, PlannedAbsence>  $absences
     */
    private function row(Character $character, Collection $clusters, int $firstIndex, Collection $absences): CharacterAttendanceData
    {
        $attendance = [];
        $plannedAbsences = [];
        $totalReports = 0;
        $reportsAttended = 0;

        foreach ($clusters->reverse() as $index => $cluster) {
            if ($index < $firstIndex) {
                $attendance[] = null;
                $plannedAbsences[] = null;

                continue;
            }

            $presence = $cluster->players->get($character->id)?->presence;
            $isAttended = $presence?->isAttended() ?? false;

            $absence = $absences->first(fn (PlannedAbsence $absence): bool => $absence->covers($cluster->startTime));

            $attendance[] = $isAttended ? $presence : Presence::Absent;
            $plannedAbsences[] = $absence;

            if ($absence === null) {
                $totalReports++;

                if ($isAttended) {
                    $reportsAttended++;
                }
            }
        }

        return new CharacterAttendanceData(
            character: $character,
            firstAttendance: $clusters->get($firstIndex)->startTime,
            totalReports: $totalReports,
            reportsAttended: $reportsAttended,
            attendance: $attendance,
            plannedAbsences: $plannedAbsences,
        );
    }
}
