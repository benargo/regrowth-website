<?php

namespace App\Actions\Raid;

use App\Actions\GameVersion\ListSelectableGameVersions;
use App\Enums\BannerPosition;
use App\Models\GameVersion;
use App\Models\Phase;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds the select options for the raid create and edit forms: the phases
 * grouped under their game versions in selectable order (versions without
 * phases left out), then the phases with no game version, each group in
 * release order; and the banner positions.
 */
class BuildRaidFormOptions
{
    use AsAction;

    /**
     * @return array{
     *     banner_positions: list<array{value: string, label: string}>,
     *     phases: list<array{label: string, options: list<array{value: int, label: string}>}>
     * }
     */
    public function handle(): array
    {
        $phasesByGameVersion = Phase::inReleaseOrder()->get()->groupBy('game_version_id');

        $groups = ListSelectableGameVersions::run()
            ->filter(fn (GameVersion $gameVersion): bool => $phasesByGameVersion->has($gameVersion->id))
            ->map(fn (GameVersion $gameVersion): array => $this->group($gameVersion->title, $phasesByGameVersion->get($gameVersion->id)))
            ->values()
            ->all();

        if ($phasesByGameVersion->has('')) {
            $groups[] = $this->group('No game version', $phasesByGameVersion->get(''));
        }

        return [
            'banner_positions' => BannerPosition::options(),
            'phases' => $groups,
        ];
    }

    /**
     * @param  Collection<int, Phase>  $phases
     * @return array{label: string, options: list<array{value: int, label: string}>}
     */
    private function group(string $label, Collection $phases): array
    {
        return [
            'label' => $label,
            'options' => $phases
                ->map(fn (Phase $phase): array => ['value' => $phase->id, 'label' => $phase->name])
                ->values()
                ->all(),
        ];
    }
}
