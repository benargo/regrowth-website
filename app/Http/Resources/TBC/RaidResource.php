<?php

namespace App\Http\Resources\TBC;

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
            'difficulty' => $this->difficulty,
            'max_players' => $this->max_players,
            'phase' => $this->whenLoaded('phase'),
            'bosses' => $this->whenLoaded('bosses'),
            'items' => $this->whenLoaded('items'),
            'comments' => $this->whenLoaded('comments'),
        ];
    }
}
