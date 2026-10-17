<?php

namespace App\Http\Resources;

use App\Attributes\Http\Resources\ScopeDefinition;
use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\HasScopes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a playable class, optionally limited to the `attendance` scope.
 */
class PlayableClassResource extends JsonResource implements ScopedResource
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
            'icon_url' => $this->getFirstMediaUrl('blizzard_icons') ?: null,
            'specializations' => $this->whenLoaded('specializations', fn () => PlayableSpecializationResource::collection($this->specializations)),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function scopeDefinitions(): array
    {
        return ['attendance' => $this->attendance()];
    }

    /**
     * Id, name and icon URL, for the attendance matrix.
     *
     * @return list<string>
     */
    #[ScopeDefinition]
    protected function attendance(): array
    {
        return ['id', 'name', 'icon_url'];
    }
}
