<?php

namespace App\Http\Resources;

use App\Contracts\Http\Resources\ScopedResource;
use App\Http\Resources\Concerns\HasScopes;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Transform a boss, optionally limited to the `management` or `navigation`
 * scope. Images are listed by media id and URL; the `management` scope lists
 * them only when the media relation is loaded, and the `navigation` scope
 * leaves them out. The raid is included only when loaded, limited in the
 * `management` scope to its id, name, colour and banner. The management
 * fields are omitted unless counted, loaded or attached. The `navigation`
 * scope exposes the name, order, comment count and strategy flag for a
 * raid's boss list.
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
        $isScoped = $this->activeScope() !== null;

        return $this->scoped([
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'notes' => $this->notes,
            'images' => $this->when(
                ! $isScoped || ($isManagement && $this->relationLoaded('media')),
                fn (): array => $this->getMedia()
                    ->map(fn (Media $media): array => ['id' => $media->id, 'url' => $media->getUrl()])
                    ->values()
                    ->all(),
            ),
            'sort_order' => $this->sort_order,
            'raid' => $this->whenLoaded('raid', fn () => $this->raid->toResource()),
            'items' => $this->whenLoaded('items', fn () => ItemResource::collection($this->items)),
            'comments_count' => $this->whenCounted('comments'),
            $this->mergeWhen($isManagement, fn (): array => [
                'items_count' => $this->whenCounted('items'),
                'links' => $this->when($this->links !== null, fn (): ?array => $this->links),
            ]),
            'meta' => $this->when(
                $isScoped && $this->resource->hasAttribute('media_count'),
                fn (): array => ['has_strategy' => filled($this->notes) || $this->media_count > 0],
            ),
            $this->mergeWhen($isManagement, fn (): array => [
                'past_assignments_count' => $this->whenCounted('pastAssignments'),
                'past_events_count' => $this->whenCounted('pastEvents'),
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
                'id', 'images', 'items_count', 'links', 'meta', 'name', 'notes', 'past_assignments_count',
                'past_events_count', 'raid.banner_position', 'raid.banner_url', 'raid.color', 'raid.id', 'raid.name',
                'slug', 'sort_order', 'template_events_count', 'upcoming_assignment_groups_count', 'upcoming_assignments_count',
                'upcoming_events_count',
            ],
            'navigation' => ['comments_count', 'id', 'meta', 'name', 'slug', 'sort_order'],
        ];
    }
}
