<?php

namespace App\Actions\Events;

use App\Actions\Discord\FetchChannel;
use App\Http\Resources\EventResource;
use Exception;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Attaches the event's Discord channel (id, name and position) to its
 * resource, or null when it has none. A failed lookup is reported and skipped.
 */
class AttachEventChannel
{
    use AsAction;

    public function handle(EventResource $resource): EventResource
    {
        $channelId = $resource->resource->channel_id;

        if (blank($channelId)) {
            return $resource->withChannel(null);
        }

        try {
            return $resource->withChannel(FetchChannel::run($channelId)->only('id', 'name', 'position')->toArray());
        } catch (Exception $e) {
            report($e);

            return $resource;
        }
    }
}
