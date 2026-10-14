<?php

namespace App\Http\Controllers\Api\Event;

use App\Actions\Discord\FetchChannel;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Notifications\RaidAssignmentsPublished;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;

#[Authorize('update', 'event')]
class PublishAssignmentsController extends Controller
{
    /**
     * Dispatch the RaidAssignmentsPublished notification for the given event.
     */
    public function __invoke(Request $request, Event $event): Response
    {
        $channel = FetchChannel::run($event->channel_id);

        $channel->notify(
            (new RaidAssignmentsPublished($event))->withSender($request->user()),
        );

        return response()->noContent();
    }
}
