<?php

namespace App\Services\RaidHelper;

use App\Services\RaidHelper\Exceptions\NoEventsFoundException;
use App\Services\RaidHelper\Resources\Event;
use App\Services\RaidHelper\Resources\PostedEvent;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class RaidHelper
{
    /**
     * The HTTP client for making requests to the Raid Helper API.
     */
    protected RaidHelperClient $client;

    /**
     * The Discord server ID for the Raid Helper service.
     */
    protected string $server_id;

    /**
     * The Discord channel IDs for the Raid Helper service.
     */
    protected array $channel_ids = [];

    /**
     * Create a new instance of the RaidHelper service.
     *
     * @param  RaidHelperClient  $client  The HTTP client for making requests to the Raid Helper API.
     * @param  array  $config  The configuration array for the Raid Helper service.
     */
    public function __construct(RaidHelperClient $client, array $config = [])
    {
        $this->client = $client;
        $this->server_id = Arr::get($config, 'server_id', '');
        $this->channel_ids = array_merge($this->channel_ids, Arr::map(Arr::get($config, 'channel_ids', []), fn ($id) => (string) $id));
    }

    /**
     * Get a single event from the Raid Helper API.
     *
     * @param  int  $eventId  The ID of the event to retrieve.
     */
    public function getEvent(int $eventId): Event
    {
        $response = $this->client->get("/servers/{$this->server_id}/events/{$eventId}");

        return Event::from($response->json());
    }

    /**
     * Get a paginated list of events from the Raid Helper API with optional filtering parameters.
     *
     * @param  int|null  $page  The page number of results to retrieve (optional).
     * @param  bool|null  $includeSignUps  Whether to include sign-up information for each event in the response (optional).
     * @param  string|null  $channelId  A specific Discord channel ID to filter events by (optional).
     * @param  CarbonInterface|null  $startTimeFilter  A Carbon instance representing the start time to filter events by (optional).
     * @param  CarbonInterface|null  $endTimeFilter  A Carbon instance representing the end time to filter events by (optional).
     *
     * @throws NoEventsFoundException if the API response indicates that no events were found based on the provided filters.
     */
    public function getEvents(
        ?int $page = 1,
        ?bool $includeSignUps = false,
        ?string $channelId = null,
        ?CarbonInterface $startTimeFilter = null,
        ?CarbonInterface $endTimeFilter = null
    ): LengthAwarePaginator {
        $headers = [
            'Page' => $page,
        ];

        if ($includeSignUps) {
            $headers['IncludeSignUps'] = 'true';
        }

        if ($channelId) {
            $headers['ChannelFilter'] = $channelId;
        }

        if ($startTimeFilter) {
            $headers['StartTimeFilter'] = $startTimeFilter->unix();
        }

        if ($endTimeFilter) {
            $headers['EndTimeFilter'] = $endTimeFilter->unix();
        }

        $response = $this->client->get("/servers/{$this->server_id}/events", $headers)->json();

        if (Arr::get($response, 'eventCountTransmitted', 0) === 0) {
            throw new NoEventsFoundException;
        }

        $rawEvents = Arr::get($response, 'postedEvents', []);
        $events = PostedEvent::collect($rawEvents);
        $perPage = min(Arr::get($response, 'eventCountTransmitted', count($rawEvents)), 1000);

        return new LengthAwarePaginator(
            items: $events,
            total: Arr::get($response, 'eventCountOverall', count($rawEvents)),
            perPage: $perPage,
            currentPage: Arr::get($response, 'currentPage', 1),
            options: ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }
}
