<?php

namespace App\Services\RaidHelper;

use App\Services\RaidHelper\Exceptions\NoEventsFoundException;
use App\Services\RaidHelper\Resources\Comp;
use App\Services\RaidHelper\Resources\Event;
use Carbon\CarbonInterface;
use Illuminate\Pagination\Paginator;
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
    protected string $serverId;

    /**
     * The Discord channel IDs for the Raid Helper service.
     */
    protected array $channelIds = [];

    /**
     * Create a new instance of the RaidHelper service.
     *
     * @param  RaidHelperClient  $client  The HTTP client for making requests to the Raid Helper API.
     * @param  array  $config  The configuration array for the Raid Helper service.
     */
    public function __construct(RaidHelperClient $client, array $config = [])
    {
        $this->client = $client;
        $this->serverId = Arr::get($config, 'server_id', '');
        $this->channelIds = array_merge($this->channelIds, Arr::map(Arr::get($config, 'channel_ids', []), fn ($id) => (string) $id));
    }

    /**
     * Get a single event from the Raid Helper API.
     *
     * @param  int  $eventId  The ID of the event to retrieve.
     */
    public function getEvent(int $eventId): Event
    {
        $response = $this->client->get("/servers/{$this->serverId}/events/{$eventId}");

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
    ): Paginator {
        $headers = [];

        if ($page !== null) {
            $headers['Page'] = $page;
        }

        if ($includeSignUps) {
            $headers['IncludeSignUps'] = 'true';
        }

        if ($channelId) {
            $headers['ChannelFilter'] = $channelId;
        }

        if ($startTimeFilter) {
            $headers['StartTimeFilter'] = $startTimeFilter->utc()->unix();
        }

        if ($endTimeFilter) {
            $headers['EndTimeFilter'] = $endTimeFilter->utc()->unix();
        }

        $response = $this->client->get("/servers/{$this->serverId}/events", $headers)->json();

        if (Arr::get($response, 'eventsTransmitted', 0) === 0) {
            throw new NoEventsFoundException;
        }

        $rawEvents = Arr::get($response, 'postedEvents', []);
        $events = Event::collect($rawEvents);
        $eventsTransmitted = Arr::get($response, 'eventsTransmitted', count($rawEvents));

        return (new Paginator(
            items: $events,
            perPage: 1000,
            currentPage: Arr::get($response, 'currentPage', 1),
            options: ['path' => Paginator::resolveCurrentPath()],
        ))->hasMorePagesWhen($eventsTransmitted >= 1000);
    }

    /**
     * Get a single comp from the Raid Helper API.
     *
     * @param  int  $eventId  The ID of the event to retrieve the comp for.
     */
    public function getComp(int $eventId): ?Comp
    {
        $response = $this->client->get("/comps/{$eventId}");

        // If the API returns a 404 status code, it means that no comp was found for the given event ID, so we return null.
        if ($response->status() === 404) {
            return null;
        }

        return Comp::from($response->json());
    }

    /**
     * Get the Discord server ID associated with this Raid Helper instance.
     */
    public function getServerId(): string
    {
        return $this->serverId;
    }

    /**
     * Set the Discord server ID for this Raid Helper instance. Useful for overriding the server ID after instantiation,
     * such as when using the service in a context where the server ID is not known at the time of construction.
     *
     * @param  string  $serverId  The Discord server ID.
     */
    public function withServer(string $serverId): self
    {
        $this->serverId = $serverId;

        return $this;
    }
}
