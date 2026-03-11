<?php

namespace App\Services\AttendanceCalculator;

use App\Exceptions\EmptyCollectionException;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\WarcraftLogs\Report;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceCalculator
{
    public function __construct(
        protected string $timezone = 'UTC',
    ) {}

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
            ->with([
                'characters' => fn ($query) => $query->whereHas('rank', fn ($q) => $q->whereIn('id', $rankIds)),
                'linkedReports',
            ])
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
            ->with([
                'characters' => fn ($query) => $query->where('id', $character->id)->whereHas('rank', fn ($q) => $q->where('count_attendance', true)),
                'linkedReports',
            ])
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

        $report->load([
            'characters' => fn ($query) => $query->whereHas('rank', fn ($q) => $q->where('count_attendance', true)),
            'linkedReports',
        ]);

        return $this->calculate(collect([$report]));
    }

    /**
     * Calculate attendance statistics by querying the database pivot table.
     *
     * Fetches all qualifying ranks and reports, merges linked reports, and
     * calculates each character's attendance percentage from their first appearance.
     *
     * @param  Collection<int, Report>  $reports
     * @return Collection<int, CharacterAttendanceStats>
     */
    protected function calculate(Collection $reports): Collection
    {
        $records = $this->mergeLinkedReports($reports);
        $records = $this->sortRaidRecords($records);

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

        // Load planned absences for all characters appearing in these records
        $characterIds = array_column($characterInfo, 'id');

        $absencesByCharacterId = PlannedAbsence::whereIn('character_id', $characterIds)
            ->get()
            ->groupBy('character_id');

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

                // Exclude reports covered by a planned absence from both numerator and denominator
                if ($this->isCoveredByPlannedAbsence($absencesByCharacterId->get($info['id'], collect()), $record['startTime'])) {
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
     * Merge reports that are linked together in the database into single raid records.
     *
     * Reports connected via the pivot_wcl_reports_links table are treated as a single
     * raid, and their player data is merged keeping the best presence per player.
     * Uses union-find to resolve connected components within the provided collection.
     *
     * @param  Collection<int, Report>  $reports  Reports with 'characters' and 'linkedReports' eager loaded.
     * @return Collection<int, array{code: string, startTime: Carbon, zoneName: string|null, players: array<string, array{id: int, rank_id: int|null, playable_class: mixed, presence: int}>}>
     */
    public function mergeLinkedReports(Collection $reports): Collection
    {
        if ($reports->isEmpty()) {
            return collect();
        }

        $codes = $reports->pluck('code')->all();
        $codeSet = array_flip($codes);

        // Union-Find initialisation
        $parent = array_combine($codes, $codes);

        $find = function (string $code) use (&$parent, &$find): string {
            if ($parent[$code] !== $code) {
                $parent[$code] = $find($parent[$code]);
            }

            return $parent[$code];
        };

        // Union reports connected by a link that is within our fetched set
        foreach ($reports as $report) {
            foreach ($report->linkedReports as $linked) {
                if (! isset($codeSet[$linked->code])) {
                    continue;
                }

                $rootA = $find($report->code);
                $rootB = $find($linked->code);

                if ($rootA !== $rootB) {
                    $parent[$rootB] = $rootA;
                }
            }
        }

        // Group reports by their root
        $groups = [];
        foreach ($reports as $report) {
            $root = $find($report->code);
            $groups[$root][] = $report;
        }

        // Merge each group into a single raid record
        return collect($groups)->map(function (array $group): array {
            if (count($group) === 1) {
                $report = $group[0];

                return [
                    'code' => $report->code,
                    'startTime' => $report->start_time,
                    'zoneName' => $report->zone_name ?? null,
                    'players' => $report->characters->mapWithKeys(fn ($char) => [
                        $char->name => [
                            'id' => $char->id,
                            'rank_id' => $char->rank_id ?? null,
                            'playable_class' => $char->playable_class ?? null,
                            'presence' => $char->pivot->presence,
                        ],
                    ])->all(),
                ];
            }

            $sortedGroup = collect($group)->sortBy('start_time');
            $mergedPlayers = [];

            foreach ($group as $report) {
                foreach ($report->characters as $char) {
                    $current = $mergedPlayers[$char->name] ?? null;
                    $playerData = [
                        'id' => $char->id,
                        'rank_id' => $char->rank_id ?? null,
                        'playable_class' => $char->playable_class ?? null,
                        'presence' => $char->pivot->presence,
                    ];

                    if ($current === null || $this->presencePriority($playerData['presence']) > $this->presencePriority($current['presence'])) {
                        $mergedPlayers[$char->name] = $playerData;
                    }
                }
            }

            return [
                'code' => collect($group)->pluck('code')->sort()->implode('+'),
                'startTime' => $sortedGroup->first()->start_time,
                'zoneName' => $sortedGroup->first()->zone_name ?? null,
                'players' => $mergedPlayers,
            ];
        })->values();
    }

    /**
     * Determine whether a raid's start time falls within any of the character's planned absences.
     *
     * Compares by calendar date in the configured timezone, since planned absences represent days rather than exact times.
     * Returns the matching PlannedAbsence if covered, or null if not.
     *
     * @param  Collection<int, PlannedAbsence>  $absences
     */
    public function isCoveredByPlannedAbsence(Collection $absences, Carbon $startTime): ?PlannedAbsence
    {
        $raidDate = $startTime->copy()->setTimezone($this->timezone)->startOfDay();

        foreach ($absences as $absence) {
            $absenceStart = $absence->start_date->copy()->startOfDay();
            $absenceEnd = $absence->end_date?->copy()->endOfDay();

            if ($raidDate->gte($absenceStart) && ($absenceEnd === null || $raidDate->lte($absenceEnd))) {
                return $absence;
            }
        }

        return null;
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
