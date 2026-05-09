<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CharacterSummaryResource extends JsonResource
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
            'rank' => $this->whenLoaded('rank'),
            'playable_class' => [
                'name' => Arr::get($this->playable_class, 'name', 'Unknown Class'),
                'slug' => Str::slug(Arr::get($this->playable_class, 'name', 'unknown-class')),
                'icon_url' => Arr::get($this->playable_class, 'icon_url', null),
            ],
        ];
    }
}
