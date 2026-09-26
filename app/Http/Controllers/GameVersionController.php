<?php

namespace App\Http\Controllers;

use App\Actions\GameVersion\BuildGameVersionRelationships;
use App\Actions\GameVersion\UpdateGameVersion;
use App\Enums\Faction;
use App\Enums\GameVersionSetupStep;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Requests\StoreGameVersionRequest;
use App\Http\Requests\UpdateGameVersionRequest;
use App\Http\Resources\GameVersionResource;
use App\Models\GameVersion;
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
    public function index(Request $request): Response
    {
        $gameVersions = GameVersion::query()
            ->withCount(GameVersion::USAGE_RELATIONS)
            ->orderBy('release_date')
            ->get();

        return Inertia::render('Manage/GameVersions/Index', [
            'gameVersions' => GameVersionResource::collectionForManagement($gameVersions)->map->resolve($request)->all(),
        ]);
    }

    /**
     * Show the form for creating a new game version.
     */
    #[Authorize('create', GameVersion::class)]
    public function create(): Response
    {
        return Inertia::render('Manage/GameVersions/Create', [
            'options' => $this->formOptions(),
            'steps' => GameVersionSetupStep::options(),
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
    public function edit(Request $request, GameVersion $gameVersion): Response
    {
        $canEdit = $this->resolveEditLock($request, $gameVersion);

        return Inertia::render('Manage/GameVersions/Edit', [
            'gameVersion' => fn (): array => GameVersionResource::forManagement($gameVersion)->resolve($request),
            'options' => fn (): array => $this->formOptions(),
            'relationships' => fn (): array => BuildGameVersionRelationships::run($gameVersion),
            'steps' => fn (): array => GameVersionSetupStep::options(),
            ...$this->editLockProps($gameVersion, $canEdit),
        ]);
    }

    /**
     * Show one step of the new game version wizard, with the steps either side
     * of it for the back and continue links. An unknown step slug fails enum
     * route binding and returns 404. A step opened from the review page
     * (?review=1) returns there once saved instead of continuing onwards.
     */
    #[Authorize('update', 'gameVersion')]
    public function setup(Request $request, GameVersion $gameVersion, GameVersionSetupStep $step): Response
    {
        $canEdit = $this->resolveEditLock($request, $gameVersion);

        return Inertia::render('Manage/GameVersions/Setup', [
            'gameVersion' => fn (): array => GameVersionResource::forManagement($gameVersion)->resolve($request),
            'step' => $step->toOption(),
            'previousStep' => $step->previous()?->toOption(),
            'nextStep' => $step->next()?->toOption(),
            'steps' => fn (): array => GameVersionSetupStep::options(),
            'relationships' => fn (): array => BuildGameVersionRelationships::run($gameVersion),
            'returnToReview' => $request->boolean('review'),
            ...$this->editLockProps($gameVersion, $canEdit),
        ]);
    }

    /**
     * Show the final wizard step: a read-only summary of the game version's
     * details and linked records, with a link back to each step to edit it.
     */
    #[Authorize('update', 'gameVersion')]
    public function review(Request $request, GameVersion $gameVersion): Response
    {
        return Inertia::render('Manage/GameVersions/Review', [
            'gameVersion' => GameVersionResource::forManagement($gameVersion)->resolve($request),
            'steps' => GameVersionSetupStep::options(),
            'relationships' => BuildGameVersionRelationships::run($gameVersion),
        ]);
    }

    /**
     * Update the specified game version with only the fields and relationships
     * the request contains, then return to the page that sent it. Autosaves
     * (sent with an X-Autosave header) return without a flash message, so a
     * toast doesn't appear every time the officer leaves a field.
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
     * Take or renew the edit lock for an active officer. A poll from an idle
     * page (X-Edit-Idle) only reports whether the officer could edit, so an
     * unattended tab lets the lock expire instead of holding it for ever.
     */
    private function resolveEditLock(Request $request, GameVersion $gameVersion): bool
    {
        if ($request->hasHeader('X-Edit-Idle')) {
            return ! $gameVersion->isLockedForEditingBy($request->user());
        }

        return $gameVersion->acquireEditLock($request->user());
    }

    /**
     * The edit lock props shared by the edit page and the setup wizard. The
     * page polls for these alone, which is also what keeps the lock alive.
     *
     * @return array{canEdit: bool, editor: callable(): ?string}
     */
    private function editLockProps(GameVersion $gameVersion, bool $canEdit): array
    {
        return [
            'canEdit' => $canEdit,
            'editor' => fn (): ?string => $canEdit ? null : $gameVersion->editor()?->display_name,
        ];
    }

    /**
     * Build the select options shared by the create and edit forms.
     *
     * @return array{
     *     factions: list<array{value: string, label: string}>,
     *     themes: list<array{value: string, label: string}>,
     *     blizzard_namespaces: list<array{value: string, label: string}>
     * }
     */
    private function formOptions(): array
    {
        return [
            'factions' => $this->enumOptions(Faction::cases()),
            'themes' => $this->enumOptions(Theme::cases()),
            'blizzard_namespaces' => $this->enumOptions(BlizzardNamespace::cases()),
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
