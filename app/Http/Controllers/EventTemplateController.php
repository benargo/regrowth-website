<?php

namespace App\Http\Controllers;

use App\Contracts\HasBlizzardIcons;
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class EventTemplateController extends Controller implements HasBlizzardIcons
{
    /**
     * Display a listing of event templates.
     */
    #[Authorize('viewTemplates', Event::class)]
    public function index(): Response
    {
        $templates = Event::templates()->with('raids')->orderBy('title')->get();

        return Inertia::render('Manage/EventTemplates/Index', (new EventTemplateCollection($templates))->toArray(request()));
    }

    /**
     * Show the form for creating a new event template.
     */
    #[Authorize('create', Event::class)]
    public function create(): Response
    {
        return Inertia::render('Manage/EventTemplates/Create', [
            'raids' => Raid::orderBy('id')->get(),
        ]);
    }

    /**
     * Store a newly created event template in storage.
     */
    #[Authorize('create', Event::class)]
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

        return redirect()->route('management.event-templates.edit', $template);
    }

    /**
     * Edit the specified event template.
     */
    #[Authorize('update', 'template')]
    public function edit(Event $template, Request $request): Response
    {
        $template->load('raids.bosses.media', 'assignments.group');

        return Inertia::render('Manage/EventTemplates/Edit', [
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
                return SpellResource::collection(Spell::all())->resolve($request);
            }),
            'questionMarkIconUrl' => URL::signedRoute('icons.show', [
                'size' => self::BLIZZARD_ICON_SIZE,
                'name' => self::BLIZZARD_UNKNOWN_ICON.'.'.self::BLIZZARD_ICON_FILE_EXTENSION,
            ]),
        ]);
    }

    /**
     * Update the specified event template.
     */
    #[Authorize('update', 'template')]
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
    #[Authorize('delete', 'template')]
    public function destroy(Event $template): RedirectResponse
    {
        $template->delete();

        return redirect()->route('management.event-templates.index');
    }
}
