<?php

namespace App\Data\Attendance;

use App\Data\Transformers\ToResourceTransformer;
use App\Enums\Presence;
use App\Models\Character;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Mappers\CamelCaseMapper;
use Spatie\LaravelData\Resource;

/**
 * One character's point on the attendance scatter chart.
 */
final class AttendanceScatterPointData extends Resource
{
    public function __construct(
        #[WithTransformer(ToResourceTransformer::class)]
        public readonly Character $character,
        public readonly float $percentage,
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly int $raidsTotal,
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly int $raidsAttended,
        public readonly int $benched,
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly int $plannedAbsences,
        #[MapOutputName(CamelCaseMapper::class)]
        public readonly int $otherAbsences,
    ) {}

    /**
     * `raidsTotal` counts every raid with a presence, planned absences included.
     */
    public static function fromRow(CharacterAttendanceRowData $row): self
    {
        $attendance = collect($row->attendance);

        return new self(
            character: $row->characterWithPlayableClass(),
            percentage: $row->percentage,
            raidsTotal: $attendance->whereNotNull()->count(),
            raidsAttended: $attendance->filter(fn (?Presence $presence): bool => $presence === Presence::Present)->count(),
            benched: $attendance->filter(fn (?Presence $presence): bool => $presence === Presence::Benched)->count(),
            plannedAbsences: collect($row->plannedAbsences)->whereNotNull()->count(),
            otherAbsences: $attendance->filter(fn (?Presence $presence): bool => $presence === Presence::Absent)->count(),
        );
    }
}
