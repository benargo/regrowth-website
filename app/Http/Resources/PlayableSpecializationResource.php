<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayableSpecializationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role' => $this->role->value,
            'icon_url' => $this->getFirstMediaUrl('blizzard_icons') ?: null,
            'is_raid_spec' => $this->whenPivotLoaded('pivot_character_specializations', fn () => (bool) $this->pivot->is_raid_spec),
        ];
    }
}
