<?php

namespace App\Jobs\RaidHelper;

use App\Models\Character;
use App\Models\Raids\Event;
use App\Services\Discord\Discord;
use App\Services\Discord\Resources\Channel;
use App\Services\RaidHelper\Exceptions\NoEventsFoundException;
use App\Services\RaidHelper\RaidHelper;
use App\Services\RaidHelper\Resources\Event as RaidHelperEvent;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FetchEvents implements ShouldQueue
{
    use Queueable;

    /**
     * The Discord channel IDs to filter events by. If empty, events from all channels will be fetched.
     *
     * @var array<int, string>
     */
    private array $channelIds = [];

    /**
     * The start time filter for fetching events. Only events with a start time after this will be fetched.
     */
    private CarbonInterface $startTimeFilter;

    /**
     * The end time filter for fetching events. Only events with a start time before this will be fetched.
     */
    private CarbonInterface $endTimeFilter;

    /**
     * Create a new job instance.
     */
    public function __construct(
        ?array $channelIds = null,
        ?CarbonInterface $startTimeFilter = null,
        ?CarbonInterface $endTimeFilter = null,
    ) {
        $this->channelIds = $channelIds ?? config('services.raidhelper.channel_ids', []);
        $this->startTimeFilter = $startTimeFilter ?? now()->subWeek()->setTime(6, 0, 0); // Default to 1 week ago at 6:00 AM
        $this->endTimeFilter = $endTimeFilter ?? now()->addWeek()->setTime(5, 59, 59); // Default to 1 week from now at 5:59:59 AM
    }

    /**
     * Execute the job.
     */
    public function handle(Discord $discord, RaidHelper $raidHelper): void
    {
        // Step 1. Validate the channel IDs to ensure they belong to the correct server.
        $validChannels = $discord->getGuildChannels($raidHelper->getServerId())->whereIn('id', $this->channelIds)->pluck('id');

        $events = collect();

        // Step 2. Fetch events from the Raid Helper API for the valid channels and within the specified time range.
        $validChannels->map(function ($channelId) use (&$events, $raidHelper) {
            // Step 2a. Fetch the first page of events for the current channel with the specified time filters.
            try {
                $paginatedEvents = $raidHelper->getEvents(
                    channelId: $channelId,
                    startTimeFilter: $this->startTimeFilter,
                    endTimeFilter: $this->endTimeFilter,
                );
                $events = $events->merge($paginatedEvents->items());
            } catch (NoEventsFoundException $e) {
                Log::notice("No events found for channel ID $channelId with the specified time filters. Skipping to the next channel.");

                return; // Skip to the next channel if no events are found for the current channel
            }

            // Step 2b. If there are more pages of events, continue fetching until all pages have been retrieved.
            while ($paginatedEvents->hasMorePages()) {
                $paginatedEvents = $raidHelper->getEvents(
                    page: $paginatedEvents->currentPage() + 1,
                    channelId: $channelId,
                    startTimeFilter: $this->startTimeFilter,
                    endTimeFilter: $this->endTimeFilter,
                );
                $events = $events->merge($paginatedEvents->items());
            }
        });

        // Step 3. Get the comps for each event and associate them with the events in the $events collection.
        $comps = $events->mapWithKeys(function (RaidHelperEvent $event) use ($raidHelper) {
            return [$event->id => $raidHelper->getComp($event->id)];
        });

        // Step 4. Upsert the events and their associated comps into the database.
        $events->each(function (RaidHelperEvent $event) use ($comps) {
            // Step 4a. Upsert the event into the database based on the raid_helper_event_id.
            $appTimezone = config('app.timezone');
            $eventModel = Event::updateOrCreate(
                ['raid_helper_event_id' => $event->id],
                [
                    'title' => $event->title,
                    'start_time' => $event->startTime->setTimezone($appTimezone),
                    'end_time' => $event->endTime->setTimezone($appTimezone),
                    'channel_id' => $event->channelId,
                ]
            );

            // Step 4b. Sync the associated comps for the event in the database.
            $comp = $comps->get($event->id);

            if ($comp) {
                $characterSync = [];
                foreach ($comp->slots as $slot) {
                    $character = Character::where('name', $slot->name)->first();
                    if ($character) {
                        $characterSync[$character->id] = [
                            'slot_number' => $slot->slotNumber,
                            'group_number' => $slot->groupNumber,
                            'is_confirmed' => $slot->isConfirmed,
                        ];
                    }
                }
                $eventModel->characters()->syncWithoutDetaching($characterSync);
            }
        });
    }
}
