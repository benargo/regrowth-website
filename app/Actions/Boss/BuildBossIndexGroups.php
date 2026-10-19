<?php

namespace App\Actions\Boss;

use App\Actions\Raid\GroupRaidsByGameVersion;
use App\Http\Resources\BossResource;
use App\Http\Resources\GameVersionResource;
use App\Http\Resources\RaidResource;
use App\Http\Resources\ScopedResourceCollection;
use App\Models\Boss;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds the boss Index page's groups: every game version in selectable
 * order, then raids whose phase has no game version when there are any.
 * Each group lists its raids in phase release order then by name, each with
 * its bosses in encounter order, their management links, usage counts,
 * past-link counts and media count.
 */
class BuildBossIndexGroups
{
    use AsAction;

    /**
     * @return list<array{game_version: GameVersionResource|null, raids: list<array{raid: RaidResource, bosses: ScopedResourceCollection}>}>
     */
    public function handle(): array
    {
        $routes = BuildBossRoutes::make();

        $bossesByRaid = Boss::withUsageCounts()
            ->withCount(['media', 'pastAssignments', 'pastEvents', 'templateEvents'])
            ->ordered()
            ->get()
            ->groupBy('raid_id');

        return collect(GroupRaidsByGameVersion::run())
            ->map(fn (array $group): array => [
                'game_version' => $group['game_version']?->toResource(),
                'raids' => $group['raids']
                    ->map(fn (Raid $raid): array => [
                        'raid' => $raid->toResource()->scope('summary'),
                        'bosses' => $this->bosses($bossesByRaid->get($raid->id, new Collection), $routes),
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, Boss>  $bosses
     */
    private function bosses(Collection $bosses, BuildBossRoutes $routes): ScopedResourceCollection
    {
        $resources = BossResource::collection($bosses->values());

        foreach ($resources->collection as $resource) {
            $resource->withLinks($routes->links($resource->resource));
        }

        return $resources;
    }
}
