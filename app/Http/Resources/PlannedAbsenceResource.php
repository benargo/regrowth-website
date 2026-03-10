<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlannedAbsenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'character_id' => $this->character_id,
            'start_date' => $this->start_date?->format('d/m/Y'),
            'end_date' => $this->end_date?->format('d/m/Y'),
            'reason' => $this->reason,
            'created_by' => $this->created_by,
        ];
    }
}
