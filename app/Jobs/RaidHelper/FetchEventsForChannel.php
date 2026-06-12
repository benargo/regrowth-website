<?php

namespace App\Jobs\RaidHelper;

use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use App\Http\Integrations\RaidHelper\Pagination\EventsPaginator;
use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use App\Http\Integrations\RaidHelper\Requests\GetEventsRequest;
use Carbon\CarbonInterface;
use DateTime;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;

class FetchEventsForChannel implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $channelId,
        public readonly CarbonInterface $startTimeFilter,
        public readonly CarbonInterface $endTimeFilter,
    ) {}

    public function retryUntil(): DateTime
    {
        return now()->addHours(2);
    }

    public function handle(RaidHelperConnector $raidHelper): void
    {
        $request = new GetEventsRequest(
            serverId: $raidHelper->serverId(),
            includeSignUps: true,
            channelId: $this->channelId,
            startTimeFilter: $this->startTimeFilter,
            endTimeFilter: $this->endTimeFilter,
        );

        $events = collect();

        try {
            foreach (new EventsPaginator(connector: $raidHelper, request: $request) as $response) {
                $events = $events->merge(
                    EventData::collect($response->json('postedEvents', []))
                );
            }
        } catch (RateLimitReachedException $e) {
            $limit = $e->getLimit();

            Log::warning('FetchEventsForChannel: Raid Helper rate limit reached, releasing job.', [
                'channel_id' => $this->channelId,
                'limit' => $limit->getName(),
                'hits' => $limit->getHits(),
                'allow' => $limit->getAllow(),
                'release_in_seconds' => $limit->getRemainingSeconds(),
            ]);

            $this->release($limit->getRemainingSeconds());

            return;
        }

        if ($events->isEmpty()) {
            Log::notice("FetchEventsForChannel: no events found for channel ID {$this->channelId}. Skipping.");

            return;
        }

        $events->each(function (EventData $event): void {
            SyncEvent::dispatch($event);
        });
    }
}
