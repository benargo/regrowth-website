<?php

namespace App\Http\Controllers;

use App\Actions\Events\AttachEventChannel;
use App\Actions\Events\BuildEventsForViewer;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Contracts\HasBlizzardIcons;
use App\Http\Middleware\RemembersRecordGameVersion;
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
use Illuminate\Contracts\Queue\QueueableCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller implements HasBlizzardIcons
{
    private const int PAST_EVENTS_PER_PAGE = 20;

    /**
     * Display the current game version's upcoming events, soonest first, then its past events,
     * newest first and paginated. Viewers without view-old-raid-plans only see past events that
     * ended within the last two weeks. `hasAnyEvents` reports whether the version has any live event.
     */
    public function index(Request $request): Response
    {
        $gameVersion = ResolveCurrentGameVersion::run();

        if ($gameVersion === null) {
            return Inertia::render('Raiding/Events/Index', [
                'hasAnyEvents' => false,
                'upcomingEvents' => EventResource::collection([]),
                'pastEvents' => EventResource::collection(new LengthAwarePaginator([], 0, self::PAST_EVENTS_PER_PAGE)),
            ]);
        }

        $events = Event::live()->where('game_version_id', $gameVersion->id);
        $canViewOldEvents = $request->user()?->isAuthorizedTo('view-old-raid-plans') ?? false;

        return Inertia::render('Raiding/Events/Index', [
            'hasAnyEvents' => $events->clone()->exists(),
            'upcomingEvents' => BuildEventsForViewer::run(
                $events->clone()->where('end_time', '>=', now())->orderBy('start_time'),
                $request,
            ),
            'pastEvents' => BuildEventsForViewer::run(
                $events->clone()
                    ->where('end_time', '<', now())
                    ->unless($canViewOldEvents, fn (Builder $query) => $query->where('end_time', '>=', now()->subWeeks(2)))
                    ->orderByDesc('start_time'),
                $request,
                self::PAST_EVENTS_PER_PAGE,
            ),
        ]);
    }

    /**
     * Display the specified resource.
     */
    #[Authorize('view', 'event')]
    #[Middleware(RemembersRecordGameVersion::class.':event')]
    public function show(Event $event, Request $request): Response
    {
        $event->load('raids.bosses.media', 'raids.media', 'bosses.media', 'assignments.group', 'characters.rank');

        return Inertia::render('Raiding/Plans/Show', [
            'event' => AttachEventChannel::run(new EventResource($event))->resolve($request),
            'questionMarkIconUrl' => URL::signedRoute('icons.show', [
                'size' => self::DEFAULT_MEDIA_SIZE,
                'name' => self::BLIZZARD_UNKNOWN_ICON.'.jpg',
            ]),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    #[Middleware('auth')]
    #[Authorize('update', 'event')]
    public function edit(Event $event, Request $request): Response
    {
        $event->load('raids.bosses.media', 'raids.media', 'bosses.media', 'assignments.group', 'characters.rank');

        return Inertia::render('Raiding/Plans/Edit', [
            'event' => AttachEventChannel::run(new EventResource($event))->resolve($request),
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
            'templates' => $this->loadTemplatesForEvent($event)->all(),
            'questionMarkIconUrl' => URL::signedRoute('icons.show', [
                'size' => self::DEFAULT_MEDIA_SIZE,
                'name' => self::BLIZZARD_UNKNOWN_ICON.'.'.self::DEFAULT_MEDIA_FILE_EXTENSION,
            ]),
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
    #[Middleware('auth')]
    #[Authorize('update', 'event')]
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
}
