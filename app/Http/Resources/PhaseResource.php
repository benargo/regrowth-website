<?php

namespace App\Http\Resources;

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
            'number' => $this->number,
            'name' => "Phase {$this->number}",
            'description' => $this->description,
            'start_date' => $this->start_date?->toIso8601String(),
            'has_started' => $this->hasStarted(),
            'game_version' => $this->whenLoaded('gameVersion', fn () => $this->gameVersion?->toResource()->resolve($request)),
            'raids' => $this->whenLoaded('raids', fn () => $this->raids->toResourceCollection(RaidResource::class)->resolve($request)),
        ];
    }
}
