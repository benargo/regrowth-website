<?php

namespace App\Actions\Phase;

use App\Actions\GameVersion\ListSelectableGameVersions;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\PhaseResource;
use App\Models\GameVersion;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds the phase Index page's groups: every game version in
 * selectable order, each with its phases in release order, then the phases with no game version when there
 * are any. Each phase carries its management links, whether it is its
 * version's current phase, its usage counts and its raids.
 */
class BuildPhaseIndexGroups
{
    use AsAction;

    /**
     * @return list<array{game_version: GameVersionResource|null, phases: AnonymousResourceCollection}>
     */
    public function handle(BuildPhaseRoutes $routes): array
    {
        $phasesByGameVersion = Phase::with(['raids' => fn (HasMany $query) => $query->orderBy('name'), 'raids.media'])
            ->withUsageCounts()
            ->inReleaseOrder()
            ->get()
            ->groupBy('game_version_id');

        $groups = ListSelectableGameVersions::run()
            ->map(fn (GameVersion $gameVersion): array => $this->group($gameVersion, $phasesByGameVersion->get($gameVersion->id, new Collection), $routes))
            ->all();

        $unownedPhases = $phasesByGameVersion->get('', new Collection);

        if ($unownedPhases->isNotEmpty()) {
            $groups[] = $this->group(null, $unownedPhases, $routes);
        }

        return $groups;
    }

    /**
     * @param  Collection<int, Phase>  $phases
     * @return array{game_version: GameVersionResource|null, phases: AnonymousResourceCollection}
     */
    private function group(?GameVersion $gameVersion, Collection $phases, BuildPhaseRoutes $routes): array
    {
        $currentPhase = $phases->filter(fn (Phase $phase): bool => $phase->has_started)->last();
        $resources = PhaseResource::collection($phases->values());

        $resources->collection->each(
            fn (PhaseResource $resource): PhaseResource => $resource
                ->withLinks($routes->links($resource->resource))
                ->markCurrent($currentPhase?->is($resource->resource) ?? false),
        );

        return [
            'game_version' => $gameVersion?->toResource(),
            'phases' => $resources,
        ];
    }
}
