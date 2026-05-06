<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventBenchedCharactersResource extends JsonResource
{
    /**
     * @var Event
     */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        $maxPlayers = $this->resource->raids()->max('max_players') ?? 0;
        $maxSlot = $this->resource->characters()->max('slot_number') ?? 0;
        $maxGroups = $maxSlot > 0 ? (int) ceil($maxPlayers / $maxSlot) : 0;
        $isTeam = $maxSlot > 5;

        if ($isTeam) {
            return [];
        }

        return $this->resource->characters()
            ->with('rank')
            ->orderBy('group_number')
            ->orderBy('slot_number')
            ->get()
            ->filter(fn ($character) => $character->pivot->group_number > $maxGroups)
            ->map(fn ($character) => [
                'id' => $character->id,
                'name' => $character->name,
                'playable_class' => $character->playable_class,
                'playable_race' => $character->playable_race,
                'rank' => new GuildRankResource($character->rank),
                'is_confirmed' => $character->pivot->is_confirmed,
            ])
            ->values()
            ->all();
    }
}
