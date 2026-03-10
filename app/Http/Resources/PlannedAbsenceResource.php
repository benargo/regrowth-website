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
            'character' => $this->whenLoaded('character', fn () => new CharacterResource($this->character)),
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'reason' => $this->reason,
            'created_by' => $this->whenLoaded('createdBy', fn () => new UserResource($this->createdBy)),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
