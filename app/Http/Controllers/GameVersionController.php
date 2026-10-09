<?php

namespace App\Http\Controllers;

use App\Actions\Datasets\ResolveEditLock;
use App\Actions\GameVersion\BuildGameVersionRelationships;
use App\Actions\GameVersion\BuildGameVersionRoutes;
use App\Actions\GameVersion\UpdateGameVersion;
use App\Enums\Faction;
use App\Enums\GameVersionSetupStep;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Requests\StoreGameVersionRequest;
use App\Http\Requests\UpdateGameVersionRequest;
use App\Http\Resources\GameVersionResource;
use App\Models\GameVersion;
use App\Models\WarcraftLogs\Guild;
use BackedEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class GameVersionController extends Controller
{
    /**
     * Display a listing of game versions.
     */
    #[Authorize('viewAny', GameVersion::class)]
    public function index(Request $request, BuildGameVersionRoutes $routes): Response
    {
        $gameVersions = GameVersion::with('warcraftLogsGuild')
            ->withUsageCounts()
            ->orderBy('release_date')
            ->get();

        return Inertia::render('Manage/GameVersions/Index', [
            'gameVersions' => GameVersionResource::collectionForManagement($gameVersions)
                ->map(fn (GameVersionResource $resource): array => [
                    ...$resource->resolve($request),
                    'links' => $routes->links($resource->resource),
                ])
                ->all(),
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
    public function store(StoreGameVersionRequest $request): RedirectResponse
    {
        $gameVersion = GameVersion::create($request->validated());

        return Redirect::route('management.game-versions.setup', [$gameVersion, GameVersionSetupStep::first()])
            ->with('success', "Added {$gameVersion->title}.");
    }

    /**
     * Show the form for editing the specified game version and its relationships.
     */
    #[Authorize('update', 'gameVersion')]
    public function edit(Request $request, GameVersion $gameVersion, BuildGameVersionRoutes $routes): Response
    {
        $editLock = ResolveEditLock::run($request, $gameVersion);

        return Inertia::render('Manage/GameVersions/Edit', [
            'gameVersion' => fn (): array => GameVersionResource::forManagement($gameVersion->loadMissing('warcraftLogsGuild'))->resolve($request),
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
    #[Authorize('update', 'gameVersion')]
    public function setup(
        Request $request,
        GameVersion $gameVersion,
        GameVersionSetupStep $step,
        BuildGameVersionRoutes $routes,
    ): Response {
        $editLock = ResolveEditLock::run($request, $gameVersion);

        return Inertia::render('Manage/GameVersions/Setup', [
            'gameVersion' => fn (): array => GameVersionResource::forManagement($gameVersion->loadMissing('warcraftLogsGuild'))->resolve($request),
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
    #[Authorize('update', 'gameVersion')]
    public function review(Request $request, GameVersion $gameVersion, BuildGameVersionRoutes $routes): Response
    {
        return Inertia::render('Manage/GameVersions/Review', [
            'gameVersion' => GameVersionResource::forManagement($gameVersion->loadMissing('warcraftLogsGuild'))->resolve($request),
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
        if ($gameVersion->isLockedForEditingBy($request->user())) {
            return Redirect::back()
                ->with('error', "Someone is editing {$gameVersion->title}, so it can't be deleted right now.");
        }

        if ($gameVersion->isInUse()) {
            return Redirect::back()
                ->with('error', "{$gameVersion->title} is still linked to other records and cannot be deleted.");
        }

        $gameVersion->delete();

        return Redirect::route('management.game-versions.index')
            ->with('success', "Deleted {$gameVersion->title}.");
    }

    /**
     * Build the select options shared by the create and edit forms.
     *
     * @return array{
     *     factions: list<array{value: string, label: string}>,
     *     themes: list<array{value: string, label: string}>,
     *     blizzard_namespaces: list<array{value: string, label: string}>,
     *     warcraft_logs_guilds: list<array{value: int, label: string}>
     * }
     */
    private function formOptions(): array
    {
        return [
            'factions' => $this->enumOptions(Faction::cases()),
            'themes' => $this->enumOptions(Theme::cases()),
            'blizzard_namespaces' => $this->enumOptions(BlizzardNamespace::cases()),
            'warcraft_logs_guilds' => Guild::orderBy('id')
                ->get()
                ->map(fn (Guild $guild): array => [
                    'value' => $guild->id,
                    'label' => "{$guild->id} ({$guild->namespace->label()})",
                ])
                ->all(),
        ];
    }

    /**
     * Map backed enum cases to value/label pairs for a select input.
     *
     * @param  list<BackedEnum>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function enumOptions(array $cases): array
    {
        return collect($cases)
            ->map(fn (BackedEnum $case): array => [
                'value' => $case->value,
                'label' => Str::ucfirst($case->value),
            ])
            ->all();
    }
}
