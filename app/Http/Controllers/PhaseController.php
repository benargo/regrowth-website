<?php

namespace App\Http\Controllers;

use App\Actions\Datasets\DeleteDatasetRecord;
use App\Actions\Datasets\ResolveEditLock;
use App\Actions\GameVersion\ResolveCurrentGameVersion;
use App\Actions\Phase\BuildPhaseFormOptions;
use App\Actions\Phase\BuildPhaseIndexGroups;
use App\Actions\Phase\BuildPhaseRoutes;
use App\Actions\Phase\ReorderPhases;
use App\Actions\Phase\UpdatePhase;
use App\Attributes\Http\Controllers\ScopesResourceTo;
use App\Http\Middleware\RemembersCurrentGameVersion;
use App\Http\Requests\ReorderPhasesRequest;
use App\Http\Requests\StorePhaseRequest;
use App\Http\Requests\UpdatePhaseRequest;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\PhaseResource;
use App\Models\GameVersion;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class PhaseController extends Controller
{
    /**
     * Display the current game version's phases, newest release selected by
     * default, alongside the phases with no game version.
     */
    #[Middleware(RemembersCurrentGameVersion::class)]
    #[ScopesResourceTo(PhaseResource::class, 'management')]
    #[Authorize('viewAny', Phase::class)]
    public function index(BuildPhaseRoutes $routes): Response
    {
        $gameVersions = GameVersion::orderByDesc('release_date')->get();
        $gameVersion = ResolveCurrentGameVersion::run($gameVersions);

        return Inertia::render('Manage/Phases/Index', [
            'gameVersion' => $gameVersion?->toResource(),
            'gameVersions' => GameVersionResource::collection($gameVersions),
            'groups' => BuildPhaseIndexGroups::run($gameVersion, $routes),
            'routes' => $routes->forIndex(),
        ]);
    }

    /**
     * Show the form for adding a phase, with the game version given in the
     * query (?game_version=) preselected.
     */
    #[Authorize('create', Phase::class)]
    public function create(Request $request, BuildPhaseRoutes $routes): Response
    {
        return Inertia::render('Manage/Phases/Create', [
            'gameVersionId' => GameVersion::find($request->integer('game_version'))?->id,
            'options' => BuildPhaseFormOptions::run(),
            'routes' => $routes->forCreate(),
        ]);
    }

    /**
     * Store a new phase and continue to its Edit page.
     */
    #[Authorize('create', Phase::class)]
    public function store(StorePhaseRequest $request, BuildPhaseRoutes $routes): RedirectResponse
    {
        $phase = Phase::create($request->validated());

        return Redirect::to($routes->afterStore($phase))
            ->with('success', "Added {$phase->name}.");
    }

    /**
     * Show the autosaving form for editing a phase, with its raids read-only.
     */
    #[ScopesResourceTo(PhaseResource::class, 'management')]
    #[Authorize('update', 'phase')]
    public function edit(Request $request, Phase $phase, BuildPhaseRoutes $routes): Response
    {
        $editLock = ResolveEditLock::run($request, $phase);

        return Inertia::render('Manage/Phases/Edit', [
            'phase' => fn (): PhaseResource => $phase
                ->loadMissing(['gameVersion', 'raids' => fn (HasMany $query) => $query->orderBy('name'), 'raids.media'])
                ->toResource(),
            'options' => fn (): array => BuildPhaseFormOptions::run(),
            'routes' => fn (): array => $routes->forEdit($phase),
            ...$editLock,
        ]);
    }

    /**
     * Update the phase with the fields in the request.
     */
    #[Authorize('update', 'phase')]
    public function update(UpdatePhaseRequest $request, Phase $phase): RedirectResponse
    {
        UpdatePhase::run($phase, $request->validated());

        if ($request->hasHeader('X-Autosave')) {
            return Redirect::back();
        }

        return Redirect::back()->with('success', "Saved changes to {$phase->name}.");
    }

    /**
     * Store the order of one game version's unscheduled phases.
     */
    #[Authorize('edit-datasets')]
    public function reorder(ReorderPhasesRequest $request): RedirectResponse
    {
        ReorderPhases::run($request->validated('phase_ids'));

        return Redirect::back();
    }

    /**
     * Remove the phase, unless another officer is editing it or raids or
     * reports still belong to it.
     */
    #[Authorize('delete', 'phase')]
    public function destroy(Request $request, Phase $phase): RedirectResponse
    {
        $refusal = DeleteDatasetRecord::run($request->user(), $phase, $phase->name);

        if ($refusal !== null) {
            return Redirect::back()->with('error', $refusal);
        }

        return Redirect::route('management.phases.index')
            ->with('success', "Deleted {$phase->name}.");
    }
}
