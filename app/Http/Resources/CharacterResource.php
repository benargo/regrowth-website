<?php

namespace App\Http\Resources;

use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CharacterResource extends JsonResource
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = Character::class;

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
            'is_main' => $this->is_main,
            'is_loot_councillor' => $this->is_loot_councillor,
            'reached_level_cap_at' => $this->reached_level_cap_at,
            'planned_absences' => $this->whenLoaded('plannedAbsences', fn () => PlannedAbsenceResource::collection($this->plannedAbsences)),
            'playable_class' => $this->playable_class,
            'playable_race' => $this->playable_race,
            'pivot' => $this->whenPivotLoaded('pivot_characters_raid_reports', fn () => [
                'presence' => $this->pivot->presence,
                'is_loot_councillor' => $this->pivot->is_loot_councillor,
            ]),
            'rank' => $this->whenLoaded('rank', fn () => new GuildRankResource($this->rank)),
        ];
    }
}
