<?php

namespace App\Http\Controllers\Api\Event;

use App\Http\Controllers\Controller;
use App\Models\DiscordNotification;
use App\Models\Event;
use App\Notifications\RaidAssignmentsPublished;
use App\Services\Discord\Discord;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Resources\Channel;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class PublishAssignmentsController extends Controller
{
    public function __construct(
        protected readonly Discord $discord,
    ) {}

    /**
     * Dispatch the RaidAssignmentsPublished notification for the given event.
     */
    public function __invoke(Request $request, Event $event): Response
    {
        $channel = $this->getChannel($event);

        $channel->notify(
            (new RaidAssignmentsPublished($event))
                ->updatesExisting($this->getExistingNotification($event))
                ->withSender($request->user()),
        );

        return response()->noContent();
    }

    protected function getExistingNotification(Event $event): ?DiscordNotification
    {
        return DiscordNotification::where('type', RaidAssignmentsPublished::class)
            ->whereHas('relatedModels', fn ($q) => $q
                ->where('model_type', Event::class)
                ->where('model_id', $event->getKey())
            )
            ->latest()
            ->first();
    }

    /**
     * Get the NotifiableChannel instance for the given event's Discord channel, using caching to optimize repeated lookups.
     */
    protected function getChannel(Event $event): NotifiableChannel
    {
        $channel = Channel::from(Cache::tags('discord')->remember("channel:{$event->channel_id}", now()->endOfDay(), function () use ($event) {
            return $this->discord->getChannel($event->channel_id)->toArray();
        }));

        return new NotifiableChannel($channel);
    }
}
