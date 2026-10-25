<?php

namespace App\Http\Controllers;

use App\Actions\Datasets\DeleteDatasetRecord;
use App\Actions\Datasets\ResolveEditLock;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Actions\Raid\BuildRaidFormOptions;
use App\Actions\Raid\BuildRaidIndexGroups;
use App\Actions\Raid\BuildRaidRoutes;
use App\Actions\Raid\ListPhasesWithRaids;
use App\Actions\Raid\UpdateRaid;
use App\Attributes\Http\Controllers\ScopesResourceTo;
use App\Contracts\Http\Controllers\SharesPriorityWeightThreshold;
use App\Enums\BannerPosition;
use App\Enums\RaidSetupStep;
use App\Enums\SiteSection;
use App\Http\Middleware\EnsureRecordSlugIsValid;
use App\Http\Middleware\RemembersCurrentRaid;
use App\Http\Middleware\RemembersRecordGameVersion;
use App\Http\Requests\StoreRaidRequest;
use App\Http\Requests\UpdateRaidRequest;
use App\Http\Resources\BossResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\PhaseResource;
use App\Http\Resources\RaidResource;
use App\Models\Comment;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard', except: ['browse', 'show', 'trash'])]
class RaidController extends Controller implements SharesPriorityWeightThreshold
{
    /**
     * Display the raids grouped by game version, then those whose phase has none.
     */
    #[ScopesResourceTo(RaidResource::class, 'management')]
    #[Authorize('viewAny', Raid::class)]
    public function index(): Response
    {
        return Inertia::render('Manage/Raids/Index', [
            'groups' => BuildRaidIndexGroups::run(),
            'routes' => BuildRaidRoutes::make()->forIndex(),
        ]);
    }

    /**
     * Display the current game version's raids, grouped by phase, for the route's section.
     */
    public function browse(Request $request): Response
    {
        $gameVersion = ResolveCurrentGameVersion::run();

        return Inertia::render('Raids/Index', [
            'section' => SiteSection::fromRoute($request),
            'phases' => PhaseResource::collection($gameVersion === null ? new Collection : ListPhasesWithRaids::run($gameVersion)),
        ]);
    }

    /** Display a raid's bosses for the route's section, redirecting to the canonical slug. */
    #[Middleware(RemembersCurrentRaid::class)]
    #[Middleware(RemembersRecordGameVersion::class.':raid,raids.index')]
    #[Middleware(EnsureRecordSlugIsValid::class.':raid')]
    public function show(Request $request, Raid $raid): Response
    {
        $section = SiteSection::fromRoute($request);

        $raid->load('media')->loadExists('trashItems');
        $bosses = $raid->bosses()->withCount('media')->get();

        if ($section->showsItemComments()) {
            $bosses->loadCount('comments');
            $raid->trash_comments_count = Comment::where('commentable_type', Item::class)
                ->whereIn('commentable_id', $raid->trashItems()->select('items.id'))
                ->count();
        }

        return Inertia::render('Raids/Show', [
            'section' => $section,
            'features' => $section->features(),
            'raid' => new RaidResource($raid),
            'bosses' => BossResource::collection($bosses)->scope('navigation'),
        ]);
    }

    /** Display a raid's trash items for the route's section, redirecting to the canonical slug. */
    #[Middleware(RemembersCurrentRaid::class)]
    #[Middleware(RemembersRecordGameVersion::class.':raid,raids.index')]
    #[Middleware(EnsureRecordSlugIsValid::class.':raid')]
    public function trash(Request $request, Raid $raid): Response
    {
        $section = SiteSection::fromRoute($request);

        return Inertia::render('Raids/Trash', [
            'section' => $section,
            'raid' => new RaidResource($raid->load('media')),
            'items' => Inertia::defer(fn () => ItemResource::collection($raid->trashItems()->withLootListData()->get())),
            'priorityWeightThreshold' => self::DISPLAY_WEIGHT_THRESHOLD,
        ]);
    }

    /**
     * Show the form for adding a raid, preselecting the phase given as `?phase=`.
     */
    #[Authorize('create', Raid::class)]
    public function create(Request $request): Response
    {
        $routes = BuildRaidRoutes::make();

        return Inertia::render('Manage/Raids/Create', [
            'phaseId' => Phase::find($request->integer('phase'))?->id,
            'options' => BuildRaidFormOptions::run(),
            'steps' => $routes->steps(),
            'routes' => $routes->forCreate(),
        ]);
    }

    /**
     * Store a new raid and continue to its setup wizard.
     */
    #[Authorize('create', Raid::class)]
    public function store(StoreRaidRequest $request): RedirectResponse
    {
        $raid = Raid::create($request->validated());

        return redirect(BuildRaidRoutes::make()->afterStore($raid))->with('success', "Added {$raid->name}.");
    }

    /**
     * Show the autosaving form for editing a raid and ordering its bosses.
     */
    #[ScopesResourceTo(RaidResource::class, 'management')]
    #[Authorize('update', 'raid')]
    public function edit(Request $request, Raid $raid): Response
    {
        $routes = BuildRaidRoutes::make();
        $editLock = ResolveEditLock::run($request, $raid);

        return Inertia::render('Manage/Raids/Edit', [
            'raid' => fn (): RaidResource => $raid->loadMissing(['media', 'phase', 'bosses.media'])->toResource(),
            'options' => fn (): array => BuildRaidFormOptions::run(),
            'steps' => fn (): array => $routes->steps($raid),
            'routes' => fn (): array => $routes->forEdit($raid),
            ...$editLock,
        ]);
    }

    /**
     * Show one step of the new raid wizard.
     */
    #[ScopesResourceTo(RaidResource::class, 'management')]
    #[Authorize('update', 'raid')]
    public function setup(Request $request, Raid $raid, RaidSetupStep $step): Response
    {
        $routes = BuildRaidRoutes::make();
        $editLock = ResolveEditLock::run($request, $raid);

        return Inertia::render('Manage/Raids/Setup', [
            'raid' => fn (): RaidResource => $raid->loadMissing(['media', 'phase', 'bosses.media'])->toResource(),
            'step' => $step->toOption(),
            'previousStep' => $step->previous()?->toOption(),
            'nextStep' => $step->next()?->toOption(),
            'steps' => fn (): array => $routes->steps($raid),
            'returnToReview' => $request->boolean('review'),
            'routes' => fn (): array => $routes->forSetup($raid, $step, $request->boolean('review')),
            ...$editLock,
        ]);
    }

    /**
     * Show the final wizard step, a read-only summary of the raid.
     */
    #[ScopesResourceTo(RaidResource::class, 'management')]
    #[Authorize('update', 'raid')]
    public function review(Raid $raid): Response
    {
        $routes = BuildRaidRoutes::make();

        return Inertia::render('Manage/Raids/Review', [
            'raid' => $raid->loadMissing(['media', 'phase', 'bosses.media'])->toResource(),
            'options' => ['banner_positions' => BannerPosition::options()],
            'steps' => $routes->steps($raid),
            'routes' => $routes->forReview($raid),
        ]);
    }

    /**
     * Update the raid with the fields, banner and boss order in the request.
     */
    #[Authorize('update', 'raid')]
    public function update(UpdateRaidRequest $request, Raid $raid): RedirectResponse
    {
        UpdateRaid::run($raid, $request->validated());

        if ($request->hasHeader('X-Autosave')) {
            return back();
        }

        return back()->with('success', "Saved changes to {$raid->name}.");
    }

    /**
     * Remove the raid, unless another officer is editing it, it has bosses,
     * or an upcoming or in-progress event includes it. Its past event and
     * item links go with it.
     */
    #[Authorize('delete', 'raid')]
    public function destroy(Request $request, Raid $raid): RedirectResponse
    {
        $refusal = DeleteDatasetRecord::run($request->user(), $raid, $raid->name);

        if ($refusal !== null) {
            return back()->with('error', $refusal);
        }

        return redirect()->route('management.raids.index')->with('success', "Deleted {$raid->name}.");
    }
}
