<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventResource;
use App\Models\Event;
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
    public function show(Event $event, Request $request): Response
    {
        $eventData = Cache::tags(['raiding', 'events'])->remember(
            "events:{$event->id}:resource",
            now()->addMinutes(10),
            function () use ($event, $request) {
                $event->load('raids.bosses.media', 'assignments', 'characters.rank');

                return (new EventResource($event))->resolve($request);
            }
        );

        return Inertia::render('Events/ShowEvent', [
            'event' => $eventData,
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
