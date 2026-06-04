<?php

namespace App\Http\Resources\LootCouncil;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriorityResource extends JsonResource
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
            'title' => $this->title,
            'type' => $this->type,
            'media' => $this->getFirstMediaUrl('blizzard_icons') ?: null,
            'weight' => $this->whenPivotLoaded('lootcouncil_item_priorities', fn () => $this->pivot->weight),
        ];
    }
}
