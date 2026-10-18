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
 * first appears, holding the raid scoped to `summary` and every template that
 * raid belongs to scoped to `template`. A multi-raid template appears under
 * each of its raids.
 */
class BuildTemplateGroups
{
    use AsAction;

    /**
     * @param  Collection<int, Event>  $templates  Templates with `raids` loaded.
     * @return list<array{raid: RaidResource, templates: ScopedResourceCollection}>
     */
    public function handle(Collection $templates): array
    {
        return $templates
            ->flatMap(fn (Event $template) => $template->raids->map(fn (Raid $raid): array => ['raid' => $raid, 'template' => $template]))
            ->groupBy(fn (array $pair): int => $pair['raid']->id)
            ->map(fn ($pairs): array => [
                'raid' => $pairs->first()['raid']->toResource()->scope('summary'),
                'templates' => EventResource::collection($pairs->pluck('template'))->scope('template'),
            ])
            ->values()
            ->all();
    }
}
