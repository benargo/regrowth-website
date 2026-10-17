<?php

namespace App\Data\Attendance;

use App\Data\Transformers\ToGroupedResourceListTransformer;
use App\Data\Transformers\ToResourceListTransformer;
use App\Models\Character;
use App\Models\PlannedAbsence;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * The attendance statistics of a game version shown on the attendance dashboard.
 */
final class AttendanceDashboardStatsData extends Data
{
    /**
     * @param  Collection<string, Collection<int, Character>>  $percentageGroups  main characters keyed by attendance band: ">=80", "50-80" or "<50"
     * @param  Collection<int, Character>  $droppingOff
     * @param  Collection<int, Character>  $pickingUp
     * @param  Collection<string, Collection<int, BenchedCharacterData>>  $benchedLastWeek  keyed by guild tag name
     * @param  Collection<int, PlannedAbsence>  $upcomingAbsences
     */
    public function __construct(
        #[WithTransformer(ToGroupedResourceListTransformer::class)]
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly Collection $percentageGroups,
        #[WithTransformer(ToResourceListTransformer::class)]
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly Collection $droppingOff,
        #[WithTransformer(ToResourceListTransformer::class)]
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly Collection $pickingUp,
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly int $totalPlayers,
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly int $totalMains,
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly int $totalLinkedCharacters,
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly ?float $phaseAttendance,
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly ?float $previousPhaseAttendance,
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly Collection $benchedLastWeek,
        #[WithTransformer(ToResourceListTransformer::class)]
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly Collection $upcomingAbsences,
    ) {}

    /**
     * Statistics with no players, no phase attendance and empty lists.
     */
    public static function blank(): self
    {
        return new self(
            percentageGroups: collect(),
            droppingOff: collect(),
            pickingUp: collect(),
            totalPlayers: 0,
            totalMains: 0,
            totalLinkedCharacters: 0,
            phaseAttendance: null,
            previousPhaseAttendance: null,
            benchedLastWeek: collect(),
            upcomingAbsences: collect(),
        );
    }
}
