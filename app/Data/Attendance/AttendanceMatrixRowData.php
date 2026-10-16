<?php

namespace App\Data\Attendance;

use App\Data\Transformers\ToResourceTransformer;
use App\Enums\Presence;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Resource;

/**
 * One attendance matrix row as sent to the Matrix page.
 */
#[MapOutputName(SnakeCaseMapper::class)]
final class AttendanceMatrixRowData extends Resource
{
    /**
     * @param  array<int, Presence|null>  $attendance
     * @param  array<int, string|null>  $plannedAbsences  planned absence ids, one per raid column
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?int $rankId,
        public readonly float $percentage,
        public readonly array $attendance,
        public readonly array $plannedAbsences,
        #[WithTransformer(ToResourceTransformer::class)]
        public readonly ?PlayableClass $playableClass,
    ) {}

    public static function fromRow(CharacterAttendanceRowData $row): self
    {
        return new self(
            id: $row->character->id,
            name: $row->character->name,
            rankId: $row->character->rank_id,
            percentage: $row->percentage,
            attendance: $row->attendance,
            plannedAbsences: array_map(fn (?PlannedAbsence $absence): ?string => $absence?->id, $row->plannedAbsences),
            playableClass: $row->character->playableClass,
        );
    }
}
