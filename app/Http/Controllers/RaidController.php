<?php

namespace App\Http\Controllers;

use App\Actions\Datasets\DeleteDatasetRecord;
use App\Actions\Datasets\ResolveEditLock;
use App\Actions\Raid\BuildRaidFormOptions;
use App\Actions\Raid\BuildRaidIndexGroups;
use App\Actions\Raid\BuildRaidRoutes;
use App\Actions\Raid\UpdateRaid;
use App\Attributes\Http\Controllers\ScopesResourceTo;
use App\Enums\RaidSetupStep;
use App\Http\Requests\StoreRaidRequest;
use App\Http\Requests\UpdateRaidRequest;
use App\Http\Resources\RaidResource;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class RaidController extends Controller
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
            'raid' => fn (): RaidResource => $raid->loadMissing(['media', 'phase', 'bosses'])->toResource(),
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
            'raid' => fn (): RaidResource => $raid->loadMissing(['media', 'phase', 'bosses'])->toResource(),
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
            'raid' => $raid->loadMissing(['media', 'phase', 'bosses'])->toResource(),
            'options' => BuildRaidFormOptions::run(),
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
