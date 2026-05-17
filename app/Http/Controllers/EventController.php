<?php

namespace App\Http\Controllers;

use App\Http\Resources\CharacterSummaryResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\PlayableClassResource;
use App\Http\Resources\SpellResource;
use App\Models\Character;
use App\Models\Event;
use App\Models\PlayableClass;
use App\Models\Spell;
use App\Models\TargetMarker;
use App\Services\Blizzard\MediaService;
use Illuminate\Http\Request;
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
    public function show(Event $event, Request $request, MediaService $mediaService): Response
    {
        $event->load('raids.bosses.media', 'assignments.group', 'characters.rank');

        return Inertia::render('Events/ShowEvent', [
            'event' => (new EventResource($event))->resolve($request),
            'questionMarkIconUrl' => $this->questionMarkIconUrl($mediaService),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Event $event, Request $request, MediaService $mediaService): Response
    {
        $event->load('raids.bosses.media', 'assignments.group', 'characters.rank');

        return Inertia::render('Events/EditEvent', [
            'event' => (new EventResource($event))->resolve($request),
            'targetMarkers' => TargetMarker::all()->map(fn (TargetMarker $m) => ['slug' => $m->slug, 'name' => $m->name])->values(),
            'characters' => Inertia::optional(function () use ($request) {
                return Character::with('rank', 'playableClass')
                    ->whereRaw('level = (SELECT MAX(level) FROM characters)')
                    ->orderBy('name')
                    ->get()
                    ->toResourceCollection(CharacterSummaryResource::class)
                    ->resolve($request);
            }),
            'playableClasses' => Inertia::optional(function () use ($request) {
                return PlayableClassResource::collection(PlayableClass::orderBy('name')->get())->resolve($request);
            }),
            'spells' => Inertia::optional(function () use ($request) {
                return SpellResource::collection(Spell::with('media')->get())->resolve($request);
            }),
            'questionMarkIconUrl' => $this->questionMarkIconUrl($mediaService),
        ]);
    }

    private function questionMarkIconUrl(MediaService $mediaService): mixed
    {
        return Inertia::optional(fn () => $mediaService->get('inv_misc_questionmark'))->once();
    }
}
