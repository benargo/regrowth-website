<?php

namespace App\Http\Controllers\Api\Event;

use App\Http\Controllers\Controller;
use App\Http\Integrations\Discord\Data\Channels\ChannelStub;
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
        (new ChannelStub($event->channel_id))->notify(
            (new RaidAssignmentsPublished($event))->withSender($request->user()),
        );

        return response()->noContent();
    }
}
