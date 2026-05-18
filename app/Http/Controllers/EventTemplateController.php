<?php

namespace App\Http\Controllers;

use App\Http\Resources\CharacterSummaryResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\EventTemplateCollection;
use App\Http\Resources\PlayableClassResource;
use App\Http\Resources\RaidResource;
use App\Http\Resources\SpellResource;
use App\Models\Character;
use App\Models\Event;
use App\Models\PlayableClass;
use App\Models\Raid;
use App\Models\Spell;
use App\Models\TargetMarker;
use App\Services\Blizzard\MediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventTemplateController extends Controller
{
    /**
     * Display a listing of event templates.
     */
    public function index(): Response
    {
        $templates = Event::templates()->with('raids')->orderBy('title')->get();

        return Inertia::render('EventTemplates/Index', (new EventTemplateCollection($templates))->toArray(request()));
    }

    /**
     * Show the form for creating a new event template.
     */
    public function create(): Response
    {
        return Inertia::render('EventTemplates/Create', [
            'raids' => Raid::orderBy('id')->get(),
        ]);
    }

    /**
     * Store a newly created event template in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'raid_ids' => ['required', 'array', 'min:1'],
            'raid_ids.*' => ['integer', 'exists:raids,id'],
        ]);

        $template = Event::create([
            'title' => $validated['title'],
            'is_template' => true,
        ]);

        $template->raids()->attach($validated['raid_ids']);

        return to_route('dashboard.event-templates.edit', $template);
    }

    /**
     * Edit the specified event template.
     */
    public function edit(Event $template, Request $request, MediaService $mediaService): Response
    {
        $template->load('raids.bosses.media', 'assignments.group');

        return Inertia::render('EventTemplates/Edit', [
            'template' => (new EventResource($template))->resolve($request),
            'raids' => RaidResource::collection(Raid::orderBy('id')->get())->resolve($request),
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

    /**
     * Update the specified event template.
     */
    public function update(Request $request, Event $template): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'raid_ids' => ['required', 'array', 'min:1'],
            'raid_ids.*' => ['integer', 'exists:raids,id'],
        ]);

        $template->update(['title' => $validated['title']]);
        $template->raids()->sync($validated['raid_ids']);

        return back();
    }

    /**
     * Remove the specified event template from storage.
     */
    public function destroy(Event $template): RedirectResponse
    {
        $template->delete();

        return to_route('dashboard.event-templates.index');
    }

    private function questionMarkIconUrl(MediaService $mediaService): mixed
    {
        return Inertia::optional(fn () => $mediaService->get('inv_misc_questionmark'))->once();
    }
}
