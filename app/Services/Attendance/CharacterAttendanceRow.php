<?php

namespace App\Services\Attendance;

use App\Models\Character;
use App\Models\PlannedAbsence;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

final class CharacterAttendanceRow implements Arrayable, JsonSerializable
{
    public function __construct(
        public readonly Character $character,
        public readonly float $percentage,
        /** @var array<int, int|null> */
        public readonly array $attendance,
        /** @var array<int, PlannedAbsence|null> */
        public readonly array $plannedAbsences,
        /** @var array<int, array<int, string>>|null */
        public readonly ?array $attendanceNames = null,
    ) {}

    /**
     * @return array{id: int, name: string, rank_id: int|null, playable_class: mixed, percentage: float, attendance: array<int, int|null>, planned_absences: array<int, string|null>, attendance_names?: array<int, array<int, string>>}
     */
    public function toArray(): array
    {
        $base = [
            'id' => $this->character->id,
            'name' => $this->character->name,
            'rank_id' => $this->character->rank_id,
            'playable_class' => $this->character->playable_class,
            'percentage' => $this->percentage,
            'attendance' => $this->attendance,
            'planned_absences' => array_map(
                fn (?PlannedAbsence $absence) => $absence?->id,
                $this->plannedAbsences,
            ),
        ];

        if ($this->attendanceNames !== null) {
            $base['attendance_names'] = $this->attendanceNames;
        }

        return $base;
    }

    /**
     * @return array{id: int, name: string, rank_id: int|null, playable_class: mixed, percentage: float, attendance: array<int, int|null>, planned_absences: array<int, string|null>, attendance_names?: array<int, array<int, string>>}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
