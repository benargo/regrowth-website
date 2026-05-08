<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventGroupsResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\RaidBossesCollection;
use App\Models\Character;
use App\Models\Event;
use App\Services\RaidHelper\RaidHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): void
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event, Request $request, RaidHelper $raidHelper): Response
    {
        $event->load('assignments');

        return Inertia::render('Events/ShowEvent', [
            'event' => new EventResource($event),
            'raids' => $event->raids()->get()->toResourceCollection(),
            'benched' => Inertia::defer(function () use ($event, $raidHelper) {
                // If the event doesn't have a linked Raid Helper event or if there are no characters signed up,
                // return an empty array to avoid unnecessary API calls and processing.
                if (! $event->raid_helper_event_id) {
                    return [];
                }

                // Cache the count of characters in the comp for the event to reduce load on the database.
                $charactersInCompCount = Cache::tags(['events'])->remember(
                    "events:{$event->raid_helper_event_id}:characters_count",
                    now()->addMinutes(10),
                    function () use ($event) {
                        return $event->characters()->count();
                    }
                );

                // If there are no characters loaded in the comp for the event, we can also skip the API call
                // and return an empty array.
                if ($charactersInCompCount === 0) {
                    return [];
                }

                $benchedCharacters = Character::hydrate(
                    Cache::tags(['events'])->remember(
                        "events:{$event->raid_helper_event_id}:benched",
                        now()->addMinutes(10),
                        function () use ($event, $raidHelper) {
                            $rhEvent = $raidHelper->getEvent((int) $event->raid_helper_event_id);

                            $signUpNames = collect($rhEvent->signUps)->pluck('name');
                            $compNames = $event->characters()->pluck('characters.name');
                            $benchedNames = $signUpNames->diff($compNames);

                            return Character::whereIn('name', $benchedNames)
                                ->with('rank')
                                ->get()
                                ->toArray();
                        }
                    )
                );

                return $benchedCharacters->toResourceCollection();
            }),
            'bosses' => Inertia::defer(function () use ($event) {
                $bosses = $event->bosses()->get();

                return new RaidBossesCollection($bosses);
            }),
            'groups' => Inertia::defer(fn () => new EventGroupsResource($event)),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event): void
    {
        // TODO: Implement event editing functionality
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event): void
    {
        // TODO: Implement event update functionality
    }
}
