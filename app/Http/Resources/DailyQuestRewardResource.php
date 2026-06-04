<?php

namespace App\Http\Resources;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Transforms a reward Item loaded through the DailyQuest::rewards() pivot.
 *
 * @mixin Item
 */
class DailyQuestRewardResource extends JsonResource
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
            'quantity' => (int) $this->pivot->quantity,
            'name' => $this->name,
            'quality' => $this->quality ? Str::lower($this->quality->name) : 'common',
            'icon' => $this->getFirstMediaUrl('blizzard_icons') ?: null,
            'wowhead_url' => $this->wowhead_url,
        ];
    }
}
