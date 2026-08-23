<?php

namespace App\Http\Resources;

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
            'type' => $this->type->value,
            'media' => $this->getFirstMediaUrl('blizzard_icons') ?: null,
            'weight' => $this->whenPivotLoaded('pivot_items_priorities', fn () => $this->pivot->weight),
        ];
    }
}
