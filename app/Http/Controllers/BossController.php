<?php

namespace App\Http\Controllers;

use App\Actions\Boss\BuildBossFormOptions;
use App\Actions\Boss\BuildBossIndexGroups;
use App\Actions\Boss\BuildBossRoutes;
use App\Actions\Boss\UpdateBoss;
use App\Actions\Datasets\DeleteDatasetRecord;
use App\Actions\Datasets\ResolveEditLock;
use App\Attributes\Http\Controllers\ScopesResourceTo;
use App\Contracts\Actions\Boss\SharesRaidPageOrigins;
use App\Contracts\Http\Controllers\SharesPriorityWeightThreshold;
use App\Enums\SiteSection;
use App\Http\Middleware\EnsureRecordSlugIsValid;
use App\Http\Middleware\RemembersCurrentRaid;
use App\Http\Middleware\RemembersRecordGameVersion;
use App\Http\Requests\StoreBossRequest;
use App\Http\Requests\UpdateBossRequest;
use App\Http\Resources\BossResource;
use App\Http\Resources\ItemResource;
use App\Models\Boss;
use App\Models\Raid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard', except: ['show'])]
class BossController extends Controller implements SharesPriorityWeightThreshold, SharesRaidPageOrigins
{
    /** Display a boss for the route's section, redirecting to the canonical slug. */
    #[Middleware(RemembersCurrentRaid::class.':boss')]
    #[Middleware(RemembersRecordGameVersion::class.':boss,raids.index')]
    #[Middleware(EnsureRecordSlugIsValid::class.':boss')]
    public function show(Request $request, Boss $boss): Response
    {
        $section = SiteSection::fromRoute($request);

        $tab = $section->showsStrategy() && $request->query('tab') !== 'items' ? 'strategy' : 'items';

        return Inertia::render('Bosses/Show', [
            'section' => $section,
            'tab' => $tab,
            'boss' => new BossResource($boss->loadMissing(['media', 'raid.media'])),
            ...($tab === 'items'
                ? ['items' => Inertia::defer(fn () => ItemResource::collection($boss->items()->withLootListData()->get()))]
                : []),
            'priorityWeightThreshold' => self::DISPLAY_WEIGHT_THRESHOLD,
        ]);
    }

    /**
     * Display every game version's raids and their bosses, then the raids
     * whose phase has no game version.
     */
    #[ScopesResourceTo(BossResource::class, 'management')]
    #[Authorize('viewAny', Boss::class)]
    public function index(): Response
    {
        return Inertia::render('Manage/Bosses/Index', [
            'groups' => BuildBossIndexGroups::run(),
            'routes' => BuildBossRoutes::make()->forIndex(),
        ]);
    }

    /**
     * Show the form for adding a boss, with the raid given in the query
     * (?raid=) preselected. ?from=raid-setup or ?from=raid-edit leads back
     * to that raid's Bosses step or Edit page.
     */
    #[Authorize('create', Boss::class)]
    public function create(Request $request): Response
    {
        $raid = Raid::find($request->integer('raid'));
        $from = $request->query('from');
        $from = in_array($from, self::FROM_RAID_PAGES, true) ? $from : null;

        return Inertia::render('Manage/Bosses/Create', [
            'raidId' => $raid?->id,
            'from' => $from,
            'options' => BuildBossFormOptions::run(),
            'routes' => BuildBossRoutes::make()->forCreate($raid, $from),
        ]);
    }

    /**
     * Store a new boss at the end of its raid's encounter order, then
     * continue to its Edit page or back to the raid's Bosses step.
     */
    #[Authorize('create', Boss::class)]
    public function store(StoreBossRequest $request): RedirectResponse
    {
        $boss = new Boss($request->safe()->only(['name']));
        $boss->raid()->associate($request->validated('raid_id'));
        $boss->save();

        return redirect(BuildBossRoutes::make()->afterStore($boss, $request->validated('from')))
            ->with('success', "Added {$boss->name}.");
    }

    /**
     * Show the autosaving form for editing a boss and its strategy.
     */
    #[ScopesResourceTo(BossResource::class, 'management')]
    #[Authorize('update', 'boss')]
    public function edit(Request $request, Boss $boss): Response
    {
        $routes = BuildBossRoutes::make();
        $editLock = ResolveEditLock::run($request, $boss);

        return Inertia::render('Manage/Bosses/Edit', [
            'boss' => fn (): BossResource => $boss->loadMissing(['media', 'raid.media'])->toResource(),
            'routes' => fn (): array => $routes->forEdit($boss),
            ...$editLock,
        ]);
    }

    /**
     * Update the boss with the fields and strategy images in the request.
     */
    #[Authorize('update', 'boss')]
    public function update(UpdateBossRequest $request, Boss $boss): RedirectResponse
    {
        UpdateBoss::run($boss, $request->validated());

        if ($request->hasHeader('X-Autosave')) {
            return back();
        }

        return back()->with('success', "Saved changes to {$boss->name}.");
    }

    /**
     * Remove the boss, unless another officer is editing it, items drop from
     * it, or an upcoming or in-progress event links, assigns to or groups
     * assignments under it.
     */
    #[Authorize('delete', 'boss')]
    public function destroy(Request $request, Boss $boss): RedirectResponse
    {
        $refusal = DeleteDatasetRecord::run($request->user(), $boss, $boss->name);

        if ($refusal !== null) {
            return back()->with('error', $refusal);
        }

        return redirect()->route('management.bosses.index')
            ->with('success', "Deleted {$boss->name}.");
    }
}
