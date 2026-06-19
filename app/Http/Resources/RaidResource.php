<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RaidResource extends JsonResource
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
            'name' => $this->name,
            'slug' => $this->slug,
            'color' => $this->color,
            'background' => $this->background_css_class?->value,
            'max_players' => $this->max_players,
            'max_loot_councillors' => $this->max_loot_councillors,
            'phase_number' => $this->whenLoaded('phase', fn () => data_get($this, 'phase.number')),
            'bosses' => $this->whenLoaded('bosses', fn () => BossResource::collection($this->bosses)),
        ];
    }
}
