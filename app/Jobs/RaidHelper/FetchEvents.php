<?php

namespace App\Jobs\RaidHelper;

use App\Events\Broadcasts\CompositionChanged;
use App\Http\Resources\EventCompositionResource;
use App\Models\Character;
use App\Models\Event;
use App\Models\Raid;
use App\Services\Discord\Discord;
use App\Services\Discord\Exceptions\RateLimitedException;
use App\Services\Discord\Resources\Channel;
use App\Services\RaidHelper\Exceptions\NoEventsFoundException;
use App\Services\RaidHelper\RaidHelper;
use App\Services\RaidHelper\Resources\Event as RaidHelperEvent;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchEvents implements ShouldQueue
{
    use Queueable;

    /**
     * The timezone to use for date and time operations.
     */
    private string $timezone = 'UTC';

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
        $this->timezone = config('app.timezone', 'UTC');
    }

    /**
     * Execute the job.
     */
    public function handle(Discord $discord, RaidHelper $raidHelper): void
    {
        // Step 1. Validate the channel IDs to ensure they belong to the correct server.
        try {
            $validChannels = $discord->getGuildChannels($raidHelper->getServerId())->whereIn('id', $this->channelIds)->pluck('id');
        } catch (RateLimitedException $e) {
            Log::warning('FetchEvents: Discord rate limited fetching guild channels, releasing job.', [
                'endpoint' => $e->endpoint,
                'retry_after' => $e->retryAfter,
                'scope' => $e->scope,
            ]);
            $this->release($e->retryAfter);

            return;
        } catch (Exception $e) {
            Log::error("Failed to fetch channels from Discord API for server ID {$raidHelper->getServerId()}. Error: {$e->getMessage()}");
            $validChannels = collect(); // Proceed with an empty collection of valid channels to avoid breaking the entire job
        }

        $events = collect();

        // Step 2. Fetch events from the Raid Helper API for the valid channels and within the specified time range.
        $validChannels->map(function ($channelId) use (&$events, $raidHelper) {
            // Step 2a. Fetch the first page of events for the current channel with the specified time filters.
            try {
                $paginatedEvents = $raidHelper->getEvents(
                    includeSignUps: true,
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
                    includeSignUps: true,
                    channelId: $channelId,
                    startTimeFilter: $this->startTimeFilter,
                    endTimeFilter: $this->endTimeFilter,
                );
                $events = $events->merge($paginatedEvents->items());
            }
        });

        // Step 3. Upsert the events and their associated comps into the database.
        $events->each(function (RaidHelperEvent $event) use ($raidHelper) {
            // Step 3a. Decode the raids from the event description.
            $raidsString = str($event->description)
                ->after("-# Do not edit below this line...\n")
                ->trim();

            // Create an empty collection to hold the Raid models that will be associated with the event.
            $raids = collect();

            // If the raids string is valid JSON...
            if ($raidsString->isJson()) {
                $decoded = json_decode(stripslashes($raidsString), true);

                foreach ($decoded as $row) {
                    if (! Arr::hasAll($row, ['id', 'name'])) {
                        Log::error("Skipping raid row due to missing required keys. Expected keys: 'id', 'name'.");
                        Log::debug('Row data: '.json_encode($row));

                        continue; // Skip this row and move to the next one if required keys are missing
                    }

                    // Create a QueryBuilder instance to check for the existence of the raid based on both 'id' and 'name' to ensure data integrity.
                    $query = Raid::where('id', Arr::get($row, 'id'))->where('name', Arr::get($row, 'name'));

                    if ($query->exists()) {
                        $raids->push($query->first());
                    }
                }
            }

            // Step 3b. Upsert the event into the database based on the raid_helper_event_id.
            Log::debug("Upserting event ID {$event->id} with title '{$event->title}' into the database.");
            $eventModel = Event::updateOrCreate(
                ['raid_helper_event_id' => $event->id],
                [
                    'title' => $event->title,
                    'start_time' => $event->startTime->setTimezone($this->timezone),
                    'end_time' => $event->endTime->setTimezone($this->timezone),
                    'background_css_class' => $raids->firstWhere('background_css_class')?->background_css_class ?? null,
                    'color' => $event->color,
                    'channel_id' => $event->channelId,
                ]
            );

            // Step 3c. Sync the raids associated with each event.
            $eventModel->raids()->sync($raids->pluck('id')->all());

            // Step 3d. Fetch the comp data for the event from the Raid Helper API and sync the associated characters with their
            // comp details (slot number, group number, confirmation status).
            Log::debug("Syncing comp data for event ID {$event->id} in the database.");
            $comp = $raidHelper->getComp($event->id);

            // Create an empty array to hold the character sync data for the event.
            $characterSync = [];

            // If a valid comp is returned from the API...
            if ($comp) {
                // Step 3e. Populate characters with their assigned slots and groups based on the comp data from the API.
                Log::debug("Syncing characters for event ID {$event->id} based on comp data from Raid Helper API.");
                foreach ($comp->slots as $slot) {
                    $character = Character::where('name', $slot->name)->first();
                    if ($character) {
                        $characterSync[$character->id] = [
                            'slot_number' => $slot->slotNumber,
                            'group_number' => $slot->groupNumber,
                            'is_confirmed' => $slot->isConfirmed,
                            'is_benched' => false,
                        ];
                    }
                }

                // Step 3f. Populate benched characters: sign-ups not in the comp.
                Log::debug("Syncing benched characters for event ID {$event->id} based on data from Raid Helper API.");
                try {
                    $compNames = collect($comp->slots)->pluck('name');
                    $benchedNames = collect($event->signUps)
                        ->whereNotIn('className', ['Absence', 'Late', 'Tentative'])
                        ->pluck('name')
                        ->diff($compNames);

                    Character::whereIn('name', $benchedNames)->get()->each(function (Character $character) use (&$characterSync) {
                        if (! isset($characterSync[$character->id])) {
                            $characterSync[$character->id] = [
                                'slot_number' => null,
                                'group_number' => null,
                                'is_confirmed' => false,
                                'is_benched' => true,
                            ];
                        }
                    });
                } catch (Exception $e) {
                    Log::error("Could not fetch sign-ups for event ID {$event->id} to sync benched characters: {$e->getMessage()}");
                }
            } else {
                Log::info("No comp data found for event ID {$event->id} from Raid Helper API. Detaching all characters.");
            }

            // Step 3g. Sync the characters with their comp details into the database for the event.
            $eventModel->characters()->sync($characterSync);

            $eventModel->load(['characters.playableClass', 'characters.rank', 'raids']);
            $composition = (new EventCompositionResource($eventModel))->resolve();
            broadcast(new CompositionChanged($eventModel->id, $composition));
        });

        // Step 4. Flush the 'events' cache to ensure that any cached event data is updated with the latest information from the database.
        Cache::tags(['events'])->flush();
    }
}
