<?php

namespace App\Http\Controllers;

use App\Http\Resources\CharacterSummaryResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\PlayableClassResource;
use App\Http\Resources\SpellResource;
use App\Models\Character;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\EventAssignmentGroup;
use App\Models\PlayableClass;
use App\Models\Spell;
use App\Models\TargetMarker;
use App\Services\Blizzard\MediaService;
use Illuminate\Contracts\Queue\QueueableCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): void
    {
        // TODO: Implement listing of events
        // This should display upcoming events, with past events accessible via a filter that is toggleable.
        // The filter should be implemented client-side so that its setting is remembered on a per-user basis.
        // The events can be fetched after the initial page load via Inertia::optional. We do not need to use
        // pagination for events, as they are automatically pruned a month after they end.
        // Each event listing should show basic details such as the title, date/time, and raids, and
        // clicking on an event should navigate to the event details page.
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
            'templates' => $this->loadTemplatesForEvent($event)->all(),
            'questionMarkIconUrl' => $this->questionMarkIconUrl($mediaService),
        ]);
    }

    /**
     * Load event templates that are compatible with the given live event.
     *
     * A template is considered compatible if it has all of the same raids attached as the live event
     * (but can have additional raids attached that the live event doesn't have).
     *
     * @return QueueableCollection<int, Event>
     */
    private function loadTemplatesForEvent(Event $event): QueueableCollection
    {
        $raids = $event->raids()->get();

        return Event::templates()
            ->with('raids') // Eager load raids to avoid N+1 when filtering templates by attached raids
            ->select('id', 'title')
            ->whereAttachedTo($raids)
            ->get();
    }

    /**
     * Apply a template's groups and assignments to a live event, appending to any existing ones.
     */
    public function applyTemplate(Event $event, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'template_id' => ['required', 'string', 'exists:events,id'],
        ]);

        $template = Event::templates()->with('assignmentGroups.assignments', 'assignments')->findOrFail($validated['template_id']);

        DB::transaction(function () use ($event, $template) {
            $maxSortOrder = EventAssignmentGroup::where('event_id', $event->id)->max('sort_order') ?? 0;

            // Copy groups and their grouped assignments
            $templateGroups = $template->assignmentGroups->sortBy('sort_order');

            foreach ($templateGroups as $i => $templateGroup) {
                $newGroup = EventAssignmentGroup::create([
                    'event_id' => $event->id,
                    'boss_id' => $templateGroup->boss_id,
                    'name' => $templateGroup->name,
                    'sort_order' => $maxSortOrder + $i + 1,
                ]);

                foreach ($templateGroup->assignments->sortBy('sort_order') as $j => $assignment) {
                    EventAssignment::create([
                        'event_id' => $event->id,
                        'boss_id' => $assignment->boss_id,
                        'group_id' => $newGroup->id,
                        'sort_order' => $j + 1,
                        'left_type' => $assignment->getRawOriginal('left_type'),
                        'left_value' => $assignment->left_value,
                        'right_type' => $assignment->getRawOriginal('right_type'),
                        'right_value' => $assignment->right_value,
                    ]);
                }
            }

            // Copy ungrouped assignments
            $ungrouped = $template->assignments->whereNull('group_id')->sortBy('sort_order');
            $maxUngroupedOrder = EventAssignment::where('event_id', $event->id)->whereNull('group_id')->max('sort_order') ?? 0;

            foreach ($ungrouped as $j => $assignment) {
                EventAssignment::create([
                    'event_id' => $event->id,
                    'boss_id' => $assignment->boss_id,
                    'group_id' => null,
                    'sort_order' => $maxUngroupedOrder + $j + 1,
                    'left_type' => $assignment->getRawOriginal('left_type'),
                    'left_value' => $assignment->left_value,
                    'right_type' => $assignment->getRawOriginal('right_type'),
                    'right_value' => $assignment->right_value,
                ]);
            }
        });

        return back();
    }

    private function questionMarkIconUrl(MediaService $mediaService): mixed
    {
        return Inertia::optional(fn () => $mediaService->get('inv_misc_questionmark'))->once();
    }
}
