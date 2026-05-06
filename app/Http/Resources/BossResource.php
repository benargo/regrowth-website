<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BossResource extends JsonResource
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
            'encounter_order' => $this->encounter_order,
            'raid' => $this->when(
                $this->relationLoaded('raid'),
                fn () => $this->raid,
                fn () => $this->raid_id,
            ),
            'items' => $this->whenLoaded('items'),
            'comments' => $this->whenLoaded('comments'),
        ];
    }
}
