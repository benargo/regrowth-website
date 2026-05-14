<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'level' => $this->level,
            'rank' => $this->whenLoaded('rank', fn () => new GuildRankResource($this->rank)->toArray($request)),
            'playable_class' => $this->whenLoaded('playableClass', fn () => $this->playableClass ? (new PlayableClassResource($this->playableClass))->toArray($request) : null),
        ];
    }
}
