<?php

namespace App\Actions\Events;

use App\Http\Resources\EventResource;
use App\Http\Resources\ScopedResourceCollection;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds the events matching the query, with their raids, scoped to
 * `visitors` for guests or `members` (with Discord channels) for signed-in users.
 */
class BuildEventsForViewer
{
    use AsAction;

    /**
     * @param  Builder<Event>  $events
     */
    public function handle(Builder $events, Request $request): ScopedResourceCollection
    {
        /** @var ScopedResourceCollection $resources */
        $resources = EventResource::collection($events->clone()->with('raids.media')->get());

        if ($request->user() === null) {
            return $resources->scope('visitors');
        }

        foreach ($resources->collection as $resource) {
            AttachEventChannel::run($resource);
        }

        return $resources->scope('members');
    }
}
