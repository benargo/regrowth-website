<?php

namespace App\Http\Resources\Raid;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceScatterPointResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: int,
     *     name: string,
     *     playable_class: mixed,
     *     percentage: float,
     *     raidsTotal: int,
     *     raidsAttended: int,
     *     benched: int,
     *     plannedAbsences: int,
     *     otherAbsences: int,
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'name' => $this['name'],
            'playable_class' => $this['playable_class'],
            'percentage' => $this['percentage'],
            'raidsTotal' => $this['raidsTotal'],
            'raidsAttended' => $this['raidsAttended'],
            'benched' => $this['benched'],
            'plannedAbsences' => $this['plannedAbsences'],
            'otherAbsences' => $this['otherAbsences'],
        ];
    }
}
