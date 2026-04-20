<?php

namespace App\Services\Attendance;

use App\Http\Resources\PlannedAbsenceResource;
use App\Models\PlannedAbsence;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use JsonSerializable;

final class AttendanceMatrix implements Arrayable, JsonSerializable
{
    public function __construct(
        /** @var Collection<int, array{id: string, code: string|null, dayOfWeek: string, date: string, zoneName: string|null}> */
        public readonly Collection $raids,
        /** @var Collection<int, CharacterAttendanceRow> */
        public readonly Collection $rows,
    ) {}

    /**
     * @return array{raids: array<int, array{id: string, code: string|null, dayOfWeek: string, date: string, zoneName: string|null}>, rows: array<int, array<string, mixed>>, planned_absences: array<int, mixed>}
     */
    public function toArray(): array
    {
        $referencedAbsences = collect();

        $rows = $this->rows->map(function (CharacterAttendanceRow $row) use (&$referencedAbsences) {
            foreach ($row->plannedAbsences as $absence) {
                if ($absence instanceof PlannedAbsence) {
                    $referencedAbsences->put($absence->id, $absence);
                }
            }

            return $row->toArray();
        })->values()->all();

        return [
            'raids' => $this->raids->values()->all(),
            'rows' => $rows,
            'planned_absences' => PlannedAbsenceResource::collection($referencedAbsences->values())->resolve(),
        ];
    }

    /**
     * @return array{raids: array<int, array{id: string, code: string|null, dayOfWeek: string, date: string, zoneName: string|null}>, rows: array<int, array<string, mixed>>, planned_absences: array<int, mixed>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
