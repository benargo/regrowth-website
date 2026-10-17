<?php

namespace App\Data\Attendance;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * The attendance statistics of a game version shown on the attendance dashboard.
 */
final class AttendanceDashboardStatsData extends Data
{
    /**
     * @param  Collection<string, AnonymousResourceCollection>  $percentageGroups  main characters keyed by attendance band: ">=80", "50-80" or "<50"
     * @param  Collection<string, Collection<int, BenchedCharacterData>>  $benchedLastWeek  keyed by guild tag name
     */
    public function __construct(
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly Collection $percentageGroups,
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly AnonymousResourceCollection $droppingOff,
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly AnonymousResourceCollection $pickingUp,
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
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly AnonymousResourceCollection $upcomingAbsences,
    ) {}
}
