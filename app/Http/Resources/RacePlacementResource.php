<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a race placement, with the character scoped to `race` when loaded.
 */
class RacePlacementResource extends JsonResource
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
            'character' => $this->whenLoaded('character', fn () => $this->character->toResource()->scope('race')),
            'medal' => $this->medal,
            'position' => $this->position,
            'reached_at' => $this->reached_at?->toIso8601String(),
        ];
    }
}
