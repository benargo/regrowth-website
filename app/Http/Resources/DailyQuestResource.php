<?php

namespace App\Http\Resources;

use App\Models\DailyQuest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DailyQuest
 */
class DailyQuestResource extends JsonResource
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
            'label' => $this->display_name,
            'type' => $this->type->value,
            'instance' => $this->instance?->value,
            'icon' => $this->getFirstMediaUrl('blizzard_icons') ?: null,
            'rewards' => $this->when(
                $this->relationLoaded('rewards'),
                fn () => DailyQuestRewardResource::collection($this->rewards)->resolve($request),
            ),
        ];
    }
}
