<?php

namespace App\Jobs\RaidHelper;

use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use App\Http\Integrations\RaidHelper\Requests\GetCompositionRequest;
use App\Models\Event;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\Statuses\NotFoundException;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;

class FetchComposition implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $eventId,
    ) {}

    public function retryUntil(): DateTime
    {
        return now()->addHours(2);
    }

    public function handle(RaidHelperConnector $raidHelper): void
    {
        $event = Event::findOrFail($this->eventId);

        try {
            $comp = $raidHelper->send(new GetCompositionRequest($event->raid_helper_event_id))->dto();
        } catch (NotFoundException) {
            Log::info("FetchComposition: no comp found for event ID {$event->raid_helper_event_id}. Skipping.");

            return;
        } catch (RateLimitReachedException $e) {
            $limit = $e->getLimit();

            Log::warning('FetchComposition: Raid Helper rate limit reached, releasing job.', [
                'event_id' => $event->id,
                'raid_helper_event_id' => $event->raid_helper_event_id,
                'limit' => $limit->getName(),
                'hits' => $limit->getHits(),
                'allow' => $limit->getAllow(),
                'release_in_seconds' => $limit->getRemainingSeconds(),
            ]);

            $this->release($limit->getRemainingSeconds());

            return;
        }

        SyncComposition::dispatch($event->raid_helper_event_id, $comp);
    }
}
