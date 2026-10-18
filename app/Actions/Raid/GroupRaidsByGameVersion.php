<?php

namespace App\Actions\Raid;

use App\Actions\GameVersion\ListSelectableGameVersions;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection as BaseCollection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Groups the raids by their phase's game version: every game version in
 * selectable order, each with its raids in phase release order then by
 * name, then the raids whose phase has no game version when there are any.
 */
class GroupRaidsByGameVersion
{
    use AsAction;

    /**
     * @return list<array{game_version: GameVersion|null, raids: Collection<int, Raid>}>
     */
    public function handle(): array
    {
        $raidsByGameVersion = Phase::with(['raids' => fn (HasMany $query) => $query->orderBy('name')])
            ->inReleaseOrder()
            ->get()
            ->groupBy('game_version_id')
            ->map(fn (BaseCollection $phases): Collection => new Collection(
                $phases->flatMap(fn (Phase $phase): array => $phase->raids->values()->all())->all(),
            ));

        $groups = ListSelectableGameVersions::run()
            ->map(fn (GameVersion $gameVersion): array => [
                'game_version' => $gameVersion,
                'raids' => $raidsByGameVersion->get($gameVersion->id, new Collection),
            ])
            ->values()
            ->all();

        $unownedRaids = $raidsByGameVersion->get('', new Collection);

        if ($unownedRaids->isNotEmpty()) {
            $groups[] = ['game_version' => null, 'raids' => $unownedRaids];
        }

        return $groups;
    }
}
