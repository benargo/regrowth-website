<?php

namespace App\Services\AttendanceCalculator;

use App\Exceptions\EmptyCollectionException;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\WarcraftLogs\Report;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceCalculator
{
    /**
     * The guild ranks that count towards attendance, keyed by ID for quick access.
     *
     * @var Collection<int, CharacterAttendanceStats>
     */
    protected $ranks;

    public function wholeGuild(): Collection
    {
        $ranks = GuildRank::where('count_attendance', true)->get();

        // If no ranks are configured to count attendance, return an empty collection instead of calculating.
        if ($ranks->isEmpty()) {
            return collect();
        }

        return $this->forRanks($ranks);
    }

    /**
     * Calculate attendance for characters in the specified ranks. Only characters who have attended at least one report while in these ranks will be included.
     *
     * @param  Collection<int, GuildRank>  $ranks
     * @return Collection<int, CharacterAttendanceStats>
     *
     * @throws EmptyCollectionException if the provided collection of ranks is empty
     */
    public function forRanks(Collection $ranks): Collection
    {
        if ($ranks->isEmpty()) {
            throw new EmptyCollectionException('At least one rank must be specified to calculate attendance for specific ranks.');
        }

        $rankIds = $ranks->pluck('id');

        $reports = Report::whereHas('guildTag', fn ($query) => $query->where('count_attendance', true))
            ->with(['characters' => fn ($query) => $query->whereHas('rank', fn ($q) => $q->whereIn('id', $rankIds))])
            ->get();

        return $this->calculate($reports);
    }

    /**
     * Calculate attendance for a specific character. Only reports attended while the character was in a rank that counts towards attendance will be included.
     *
     * @return Collection<int, CharacterAttendanceStats> A collection containing a single CharacterAttendanceStats
     */
    public function forCharacter(Character $character): Collection
    {
        $reports = Report::whereHas('guildTag', fn ($query) => $query->where('count_attendance', true))
            ->whereHas('characters', fn ($query) => $query->where('id', $character->id)->whereHas('rank', fn ($q) => $q->where('count_attendance', true)))
            ->with(['characters' => fn ($query) => $query->where('id', $character->id)->whereHas('rank', fn ($q) => $q->where('count_attendance', true))])
            ->get();

        return $this->calculate($reports);
    }

    /**
     * Calculate attendance for a specific report. Only characters in the report who were in ranks that count towards attendance will be included.
     *
     * @return Collection<int, CharacterAttendanceStats>
     */
    public function forReport(Report $report): Collection
    {
        // If the report's guild tag doesn't count attendance, return an empty collection instead of calculating.
        if (! $report->guildTag?->count_attendance) {
            return collect();
        }

        $report->load(['characters' => fn ($query) => $query->whereHas('rank', fn ($q) => $q->where('count_attendance', true))]);

        return $this->calculate(collect([$report]));
    }

    /**
     * Calculate attendance statistics by querying the database pivot table.
     *
     * Fetches all qualifying ranks and reports, merges same-day raids, and
     * calculates each character's attendance percentage from their first appearance.
     *
     * @param  Collection<int, Report>  $reports
     * @return Collection<int, CharacterAttendanceStats>
     */
    protected function calculate(Collection $reports): Collection
    {
        /** @var array<int, array{code: string, startTime: Carbon, players: array<string, array{id: int, presence: int, playableClass: array|null}>}> $raidRecords */
        $raidRecords = $reports->map(fn (Report $report) => [
            'code' => $report->code,
            'startTime' => $report->start_time,
            'players' => $report->characters->mapWithKeys(fn ($character) => [
                $character->name => [
                    'id' => $character->id,
                    'presence' => $character->pivot->presence,
                ],
            ])->all(),
        ])->all();

        $records = $this->sortRaidRecords(collect($raidRecords));
        $records = $this->mergeByRaidDay($records);

        if ($records->isEmpty()) {
            return collect();
        }

        // First pass: find each character's ID and earliest attendance
        /** @var array<string, array{id: int, firstAttendance: Carbon}> $characterInfo */
        $characterInfo = [];

        foreach ($records as $record) {
            foreach ($record['players'] as $name => $playerData) {
                if (! isset($characterInfo[$name])) {
                    $characterInfo[$name] = [
                        'id' => $playerData['id'],
                        'firstAttendance' => $record['startTime'],
                    ];
                }
            }
        }

        // Second pass: for each character, count reports since their first attendance
        $stats = [];

        foreach ($characterInfo as $characterName => $info) {
            $totalReports = 0;
            $reportsAttended = 0;

            foreach ($records as $record) {
                // Only count reports on or after the character's first attendance
                if ($record['startTime']->lt($info['firstAttendance'])) {
                    continue;
                }

                $totalReports++;

                // Check if character attended this report (presence 1 or 2)
                if (isset($record['players'][$characterName]) && in_array($record['players'][$characterName]['presence'], [1, 2], true)) {
                    $reportsAttended++;
                }
            }

            $percentage = $totalReports > 0 ? ($reportsAttended / $totalReports) * 100 : 0.0;

            $stats[$characterName] = new CharacterAttendanceStats(
                id: $info['id'],
                name: $characterName,
                firstAttendance: $info['firstAttendance'],
                totalReports: $totalReports,
                reportsAttended: $reportsAttended,
                percentage: round($percentage, 2),
            );
        }

        return new Collection($stats)
            ->sortBy(fn (CharacterAttendanceStats $s) => $s->name)
            ->values();
    }

    /**
     * Merge raid records that fall on the same raid day into a single record.
     *
     * A "raid day" runs from 05:00 to 04:59 the following morning in the app timezone.
     * When multiple raids occur on the same raid day, characters are considered present
     * if they appeared in any of the merged raids, using their best presence value.
     *
     * @param  Collection<int, array{code: string, startTime: Carbon, players: array<string, array{id: int, rank_id: int|null, presence: int}>}>  $records  Sorted by startTime ascending.
     * @return Collection<int, array{code: string, startTime: Carbon, players: array<string, array{id: int, rank_id: int|null, presence: int}>}>
     */
    public function mergeByRaidDay(Collection $records): Collection
    {
        $timezone = config('app.timezone');

        return $records
            ->groupBy(fn (array $record) => $record['startTime']->copy()->setTimezone($timezone)->subHours(5)->toDateString())
            ->map(function (Collection $group) {
                if ($group->count() === 1) {
                    return $group->first();
                }

                /** @var array<string, array{id: int, presence: int}> $mergedPlayers */
                $mergedPlayers = [];

                foreach ($group as $record) {
                    foreach ($record['players'] as $name => $playerData) {
                        $current = $mergedPlayers[$name] ?? null;

                        if ($current === null || $this->presencePriority($playerData['presence']) > $this->presencePriority($current['presence'])) {
                            $mergedPlayers[$name] = $playerData;
                        }
                    }
                }

                return [
                    'code' => collect($group)->pluck('code')->implode('+'),
                    'startTime' => $group->first()['startTime'],
                    'players' => $mergedPlayers,
                ];
            })
            ->values();
    }

    /**
     * Get the priority rank for a presence value (higher = better).
     */
    public function presencePriority(int $presence): int
    {
        return match ($presence) {
            1 => 2,
            2 => 1,
            default => 0,
        };
    }

    /**
     * Get the raid records sorted by startTime ascending.
     *
     * @param  Collection<int, array{code: string, startTime: Carbon, players: array<string, array{id: int, rank_id: int|null, presence: int}>}>  $records
     * @return Collection<int, array{code: string, startTime: Carbon, players: array<string, array{id: int, rank_id: int|null, presence: int}>}>
     */
    public function sortRaidRecords(Collection $records): Collection
    {
        return $records->sortBy(fn (array $record) => $record['startTime'])->values();
    }
}
