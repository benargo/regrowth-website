<?php

namespace App\Http\Resources;

use App\Models\PlannedAbsence;
use App\Services\Attendance\CharacterAttendanceRowData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CharacterAttendanceRowData */
class AttendanceMatrixRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->character->id,
            'name' => $this->character->name,
            'rank_id' => $this->character->rank_id,
            'percentage' => $this->percentage,
            'attendance' => $this->attendance,
            'planned_absences' => array_map(
                fn (?PlannedAbsence $absence) => $absence?->id,
                $this->plannedAbsences,
            ),
        ];

        if ($this->character->relationLoaded('playableClass')) {
            $data['playable_class'] = (new PlayableClassResource($this->character->playableClass))->resolve($request);
        }

        return $data;
    }
}
