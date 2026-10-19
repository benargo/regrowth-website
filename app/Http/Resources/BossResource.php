<?php

namespace App\Http\Resources;

use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\HasScopes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Transform a boss, optionally limited to the `management` scope. The
 * management fields are omitted unless counted, loaded or attached; the
 * image URL list is left out of the `management` scope, which lists
 * `strategy_images` by media id instead.
 */
class BossResource extends JsonResource implements ScopedResource
{
    use HasScopes;

    /**
     * @var array<string, string>|null
     */
    protected ?array $links = null;

    /**
     * Attach the management action links for this boss.
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
        $isManagement = $this->activeScope() === 'management';

        return $this->scoped([
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'notes' => $this->notes,
            'images' => $this->when(! $isManagement, fn (): array => $this->getMedia()->map->getUrl()->values()->all()),
            'sort_order' => $this->sort_order,
            'raid' => $this->when(
                $this->relationLoaded('raid'),
                fn () => new RaidResource($this->raid),
                $this->raid_id,
            ),
            'items' => $this->whenLoaded('items', fn () => ItemResource::collection($this->items)),
            'comments_count' => $this->whenCounted('comments'),
            $this->mergeWhen($isManagement, fn (): array => [
                'items_count' => $this->whenCounted('items'),
                'links' => $this->when($this->links !== null, fn (): ?array => $this->links),
            ]),
            'meta' => $this->when(
                $isManagement && $this->resource->hasAttribute('media_count'),
                fn (): array => ['has_strategy' => filled($this->notes) || $this->media_count > 0],
            ),
            $this->mergeWhen($isManagement, fn (): array => [
                'past_assignments_count' => $this->whenCounted('pastAssignments'),
                'past_events_count' => $this->whenCounted('pastEvents'),
                'raid_id' => $this->raid_id,
                'strategy_images' => $this->when(
                    $this->relationLoaded('media'),
                    fn (): array => $this->getMedia()
                        ->map(fn (Media $media): array => ['id' => $media->id, 'url' => $media->getUrl()])
                        ->values()
                        ->all(),
                ),
                'template_events_count' => $this->whenCounted('templateEvents'),
                'upcoming_assignment_groups_count' => $this->whenCounted('upcomingAssignmentGroups'),
                'upcoming_assignments_count' => $this->whenCounted('upcomingAssignments'),
                'upcoming_events_count' => $this->whenCounted('upcomingEvents'),
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
                'id', 'items_count', 'links', 'meta', 'name', 'notes', 'past_assignments_count',
                'past_events_count', 'raid', 'raid_id', 'slug', 'sort_order', 'strategy_images',
                'template_events_count', 'upcoming_assignment_groups_count', 'upcoming_assignments_count',
                'upcoming_events_count',
            ],
        ];
    }
}
