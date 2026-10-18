<?php

namespace App\Http\Resources;

use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\HasScopes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a raid, optionally limited to the `summary` scope.
 */
class RaidResource extends JsonResource implements ScopedResource
{
    use HasScopes;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->scoped([
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'difficulty' => $this->difficulty,
            'color' => $this->color,
            'banner_url' => $this->whenLoaded('media', fn (): ?string => $this->banner_url),
            'banner_position' => $this->whenLoaded('media', fn (): ?string => $this->banner_position),
            'max_players' => $this->max_players,
            'max_loot_councillors' => $this->max_loot_councillors,
            'phase_number' => $this->whenLoaded('phase', fn () => data_get($this, 'phase.number')),
            'has_trash_items' => $this->whenExistsLoaded('trashItems'),
            'trash_comments_count' => $this->whenCounted('trash_comments_count'),
            'bosses' => $this->whenLoaded('bosses', fn () => $this->bosses->toResourceCollection()->resolve($request)),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function scopeDefinitions(): array
    {
        return ['summary' => ['id', 'name']];
    }
}
