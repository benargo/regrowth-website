<?php

namespace App\Http\Controllers;

use App\Http\Resources\EventBenchedCharactersResource;
use App\Http\Resources\EventGroupsResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\RaidBossesCollection;
use App\Models\Event;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Event $event, Request $request): Response
    {
        return Inertia::render('Events/ShowEvent', [
            'event' => new EventResource($event),
            'raids' => $event->raids()->get()->toResourceCollection(),
            'benched' => Inertia::defer(fn () => new EventBenchedCharactersResource($event)),
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
    public function edit(Event $event)
    {
        // TODO: Implement event editing functionality
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Event $event)
    {
        // TODO: Implement event update functionality
    }
}
