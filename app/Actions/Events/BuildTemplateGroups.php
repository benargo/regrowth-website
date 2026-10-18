<?php

namespace App\Actions\Events;

use App\Http\Resources\EventResource;
use App\Http\Resources\RaidResource;
use App\Http\Resources\ScopedResourceCollection;
use App\Models\Event;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds one group per raid in the given templates, in the order each raid
 * first appears, holding every template that raid belongs to scoped to
 * `template`. A multi-raid template appears under each of its raids.
 */
class BuildTemplateGroups
{
    use AsAction;

    /**
     * @param  Collection<int, Event>  $templates  Templates with `raids.media` loaded.
     * @return list<array{raid: RaidResource, templates: ScopedResourceCollection}>
     */
    public function handle(Collection $templates): array
    {
        return $templates
            ->flatMap(fn (Event $template) => $template->raids->map(fn (Raid $raid): array => ['raid' => $raid, 'template' => $template]))
            ->groupBy(fn (array $pair): int => $pair['raid']->id)
            ->map(fn ($pairs): array => [
                'raid' => $pairs->first()['raid']->toResource(),
                'templates' => EventResource::collection($pairs->pluck('template')->values())->scope('template'),
            ])
            ->values()
            ->all();
    }
}
