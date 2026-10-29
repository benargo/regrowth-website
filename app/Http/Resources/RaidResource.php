<?php

namespace App\Http\Resources;

use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\DescribesMediaCollections;
use App\Http\Resources\Concerns\HasScopes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transform a raid, optionally limited to the `management` or `summary`
 * scope. The management fields appear only in the management scope, where
 * `phase_id` is always included and the rest only when loaded, counted or
 * attached.
 */
class RaidResource extends JsonResource implements ScopedResource
{
    use DescribesMediaCollections;
    use HasScopes;

    /**
     * @var array<string, string>|null
     */
    protected ?array $links = null;

    /**
     * Attach the management action links for this raid.
     *
     * @param  array<string, string>  $links
     */
    public function withLinks(array $links): static
    {
        $this->links = $links;

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
            $this->mergeWhen($this->activeScope() === 'management', fn (): array => [
                'phase_id' => $this->phase_id,
                'phase' => $this->whenLoaded('phase', fn () => $this->phase->toResource()->resolve($request)),
                'bosses_count' => $this->whenCounted('bosses'),
                'upcoming_events_count' => $this->whenCounted('upcomingEvents'),
                'past_events_count' => $this->whenCounted('pastEvents'),
                'template_events_count' => $this->whenCounted('templateEvents'),
                'items_count' => $this->whenCounted('items'),
                'links' => $this->when($this->links !== null, fn (): ?array => $this->links),
            ]),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function scopeDefinitions(): array
    {
        return [
            'management' => [
                'banner_position', 'banner_url', 'bosses', 'bosses_count', 'color', 'difficulty', 'id', 'items_count', 'links',
                'max_loot_councillors', 'max_players', 'name', 'past_events_count', 'phase', 'phase_id', 'slug', 'template_events_count', 'upcoming_events_count',
            ],
            'summary' => ['id', 'name'],
        ];
    }

    /**
     * Get additional data returned with the resource in the management
     * scope: the MIME types each media collection accepts.
     *
     * @return array{media?: array<string, array{accepts: list<string>}>}
     */
    public function with(Request $request): array
    {
        if ($this->activeScope() !== 'management') {
            return [];
        }

        return [
            'media' => $this->mediaCollections(),
        ];
    }
}
