<?php

namespace App\Actions\Raid;

use App\Http\Resources\GameVersionResource;
use App\Http\Resources\RaidResource;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds the raid Index page's groups from GroupRaidsByGameVersion. Each raid
 * carries its management links, its phase, its usage counts and the counts
 * of past events, event templates and items its deletion would unlink.
 */
class BuildRaidIndexGroups
{
    use AsAction;

    /**
     * @return list<array{game_version: GameVersionResource|null, raids: AnonymousResourceCollection}>
     */
    public function handle(): array
    {
        $routes = BuildRaidRoutes::make();

        $groups = GroupRaidsByGameVersion::run();

        (new Collection(collect($groups)->flatMap(fn (array $group): array => $group['raids']->all())->all()))
            ->load(['media', 'phase'])
            ->loadCount([...(new Raid)->usageRelations(), 'pastEvents', 'templateEvents', 'items']);

        return collect($groups)
            ->map(fn (array $group): array => [
                'game_version' => $group['game_version']?->toResource(),
                'raids' => $this->raids($group['raids'], $routes),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, Raid>  $raids
     */
    private function raids(Collection $raids, BuildRaidRoutes $routes): AnonymousResourceCollection
    {
        $resources = RaidResource::collection($raids->values());

        foreach ($resources->collection as $resource) {
            $resource->withLinks($routes->links($resource->resource));
        }

        return $resources;
    }
}
