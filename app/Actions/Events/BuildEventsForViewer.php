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
 * `visitors` for guests or `members` for signed-in users. A page size paginates them.
 */
class BuildEventsForViewer
{
    use AsAction;

    /**
     * @param  Builder<Event>  $events
     */
    public function handle(Builder $events, Request $request, ?int $perPage = null): ScopedResourceCollection
    {
        $query = $events->clone()->with('raids');

        /** @var ScopedResourceCollection $resources */
        $resources = EventResource::collection(
            $perPage === null ? $query->get() : $query->paginate($perPage)->withQueryString()
        );

        if ($request->user() === null) {
            return $resources->scope('visitors');
        }

        return $resources->scope('members');
    }
}
