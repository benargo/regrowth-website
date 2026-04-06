<?php

namespace App\Http\Resources\TBC;

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
            'raid' => $this->whenLoaded('raid'),
            'items' => $this->whenLoaded('items'),
            'comments' => $this->whenLoaded('comments'),
        ];
    }
}
