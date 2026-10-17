<?php

namespace App\Http\Controllers;

use App\Actions\Datasets\DeleteDatasetRecord;
use App\Actions\Datasets\ResolveEditLock;
use App\Actions\GameVersion\BuildGameVersionRelationships;
use App\Actions\GameVersion\BuildGameVersionRoutes;
use App\Actions\GameVersion\UpdateGameVersion;
use App\Attributes\Http\Controllers\ScopesResourceTo;
use App\Enums\Faction;
use App\Enums\GameVersionSetupStep;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Requests\StoreGameVersionRequest;
use App\Http\Requests\UpdateGameVersionRequest;
use App\Http\Resources\GameVersionResource;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\Guild;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class GameVersionController extends Controller
{
    /**
     * Display a listing of game versions.
     */
    #[ScopesResourceTo(GameVersionResource::class, 'management')]
    #[Authorize('viewAny', GameVersion::class)]
    public function index(BuildGameVersionRoutes $routes): Response
    {
        $gameVersions = GameVersion::with('warcraftLogsGuild')
            ->withUsageCounts()
            ->orderBy('release_date')
            ->get();

        $gameVersionResources = GameVersionResource::collection($gameVersions);
        $gameVersionResources->collection->each(
            fn (GameVersionResource $resource): GameVersionResource => $resource->withLinks($routes->links($resource->resource)),
        );

        return Inertia::render('Manage/GameVersions/Index', [
            'gameVersions' => $gameVersionResources,
            'routes' => $routes->forIndex(),
        ]);
    }

    /**
     * Show the form for creating a new game version.
     */
    #[Authorize('create', GameVersion::class)]
    public function create(BuildGameVersionRoutes $routes): Response
    {
        return Inertia::render('Manage/GameVersions/Create', [
            'options' => $this->formOptions(),
            'steps' => $routes->steps(),
            'routes' => $routes->forCreate(),
        ]);
    }

    /**
     * Store a newly created game version and continue to the setup wizard.
     */
    #[Authorize('create', GameVersion::class)]
    public function store(StoreGameVersionRequest $request, BuildGameVersionRoutes $routes): RedirectResponse
    {
        $gameVersion = GameVersion::create($request->validated());

        return Redirect::to($routes->afterStore($gameVersion))
            ->with('success', "Added {$gameVersion->title}.");
    }

    /**
     * Show the form for editing the specified game version and its relationships.
     */
    #[ScopesResourceTo(GameVersionResource::class, 'management')]
    #[Authorize('update', 'gameVersion')]
    public function edit(Request $request, GameVersion $gameVersion, BuildGameVersionRoutes $routes): Response
    {
        $editLock = ResolveEditLock::run($request, $gameVersion);
        $gameVersion->loadMissing('warcraftLogsGuild');

        return Inertia::render('Manage/GameVersions/Edit', [
            'gameVersion' => $gameVersion->toResource(),
            'options' => fn (): array => $this->formOptions(),
            'relationships' => fn (): array => BuildGameVersionRelationships::run($gameVersion),
            'steps' => fn (): array => $routes->steps($gameVersion),
            'routes' => fn (): array => $routes->forEdit($gameVersion),
            ...$editLock,
        ]);
    }

    /**
     * Show one step of the new game version wizard.
     */
    #[ScopesResourceTo(GameVersionResource::class, 'management')]
    #[Authorize('update', 'gameVersion')]
    public function setup(
        Request $request,
        GameVersion $gameVersion,
        GameVersionSetupStep $step,
        BuildGameVersionRoutes $routes,
    ): Response {
        $editLock = ResolveEditLock::run($request, $gameVersion);
        $gameVersion->loadMissing('warcraftLogsGuild');

        return Inertia::render('Manage/GameVersions/Setup', [
            'gameVersion' => $gameVersion->toResource(),
            'step' => $step->toOption(),
            'previousStep' => $step->previous()?->toOption(),
            'nextStep' => $step->next()?->toOption(),
            'steps' => fn (): array => $routes->steps($gameVersion),
            'relationships' => fn (): array => BuildGameVersionRelationships::run($gameVersion),
            'returnToReview' => $request->boolean('review'),
            'routes' => fn (): array => $routes->forSetup($gameVersion, $step, $request->boolean('review')),
            ...$editLock,
        ]);
    }

    /**
     * Show the final wizard step, a read-only summary of the game version.
     */
    #[ScopesResourceTo(GameVersionResource::class, 'management')]
    #[Authorize('update', 'gameVersion')]
    public function review(GameVersion $gameVersion, BuildGameVersionRoutes $routes): Response
    {
        $gameVersion->loadMissing('warcraftLogsGuild');

        return Inertia::render('Manage/GameVersions/Review', [
            'gameVersion' => $gameVersion->toResource(),
            'steps' => $routes->steps($gameVersion),
            'relationships' => BuildGameVersionRelationships::run($gameVersion),
            'routes' => $routes->forReview($gameVersion),
        ]);
    }

    /**
     * Update the specified game version with the fields and relationships in the request.
     */
    #[Authorize('update', 'gameVersion')]
    public function update(UpdateGameVersionRequest $request, GameVersion $gameVersion): RedirectResponse
    {
        UpdateGameVersion::run($gameVersion, $request->validated());

        if ($request->hasHeader('X-Autosave')) {
            return Redirect::back();
        }

        return Redirect::back()->with('success', "Saved changes to {$gameVersion->title}.");
    }

    /**
     * Remove the specified game version, unless another officer is editing it
     * or dataset records still reference it.
     */
    #[Authorize('delete', 'gameVersion')]
    public function destroy(Request $request, GameVersion $gameVersion): RedirectResponse
    {
        $refusal = DeleteDatasetRecord::run($request->user(), $gameVersion, $gameVersion->title);

        if ($refusal !== null) {
            return Redirect::back()->with('error', $refusal);
        }

        return Redirect::route('management.game-versions.index')
            ->with('success', "Deleted {$gameVersion->title}.");
    }

    /**
     * Build the select options shared by the create and edit forms.
     *
     * @return array{
     *     factions: list<array{value: string, label: string}>,
     *     blizzard_namespaces: list<array{value: string, label: string}>,
     *     warcraft_logs_guilds: list<array{value: int, label: string}>
     * }
     */
    private function formOptions(): array
    {
        return [
            'factions' => Faction::options(),
            'blizzard_namespaces' => BlizzardNamespace::options(),
            'warcraft_logs_guilds' => Guild::orderBy('id')
                ->get()
                ->map(fn (Guild $guild): array => [
                    'value' => $guild->id,
                    'label' => "{$guild->id} ({$guild->namespace->label()})",
                ])
                ->all(),
        ];
    }
}
