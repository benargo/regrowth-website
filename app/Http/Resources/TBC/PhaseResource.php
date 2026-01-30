<?php

namespace App\Http\Resources\TBC;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhaseResource extends JsonResource
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
            'description' => $this->description,
            'start_date' => $this->start_date?->toIso8601String(),
            'has_started' => $this->hasStarted(),
            'raids' => $this->whenLoaded('raids'),
            'bosses' => $this->whenLoaded('bosses'),
        ];
    }
}
