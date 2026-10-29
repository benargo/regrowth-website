<?php

namespace App\Http\Resources;

use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\HasScopes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a phase, optionally limited to the `management` scope. The
 * management fields are omitted unless loaded, counted or attached.
 */
class PhaseResource extends JsonResource implements ScopedResource
{
    use HasScopes;

    /**
     * @var array<string, string>|null
     */
    protected ?array $links = null;

    protected ?bool $isCurrent = null;

    /**
     * Attach the management action links for this phase.
     *
     * @param  array<string, string>  $links
     */
    public function withLinks(array $links): static
    {
        $this->links = $links;

        return $this;
    }

    /**
     * Mark whether this phase is its game version's current phase.
     */
    public function markCurrent(bool $isCurrent): static
    {
        $this->isCurrent = $isCurrent;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->scoped([
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date?->toIso8601String(),
            'has_started' => $this->has_started,
            'is_current' => $this->when($this->isCurrent !== null, fn (): ?bool => $this->isCurrent),
            'game_version' => $this->whenLoaded('gameVersion', fn () => $this->gameVersion?->toResource()->resolve($request)),
            'raids' => $this->whenLoaded('raids', fn () => $this->raids->toResourceCollection(RaidResource::class)->resolve($request)),
            'raids_count' => $this->whenCounted('raids'),
            'reports_count' => $this->whenCounted('reports'),
            'links' => $this->when($this->links !== null, fn (): ?array => $this->links),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function scopeDefinitions(): array
    {
        return [
            'management' => [
                'id', 'number', 'name', 'description', 'start_date', 'has_started', 'is_current',
                'game_version', 'raids', 'raids_count', 'reports_count', 'links',
            ],
        ];
    }
}
