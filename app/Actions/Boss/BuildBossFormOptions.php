<?php

namespace App\Actions\Boss;

use App\Actions\Raid\GroupRaidsByGameVersion;
use App\Models\Raid;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds the select options for the boss create form: every raid, grouped
 * by its phase's game version, leaving out versions without raids.
 */
class BuildBossFormOptions
{
    use AsAction;

    /**
     * @return array{raids: list<array{label: string, options: list<array{value: int, label: string}>}>}
     */
    public function handle(): array
    {
        return [
            'raids' => collect(GroupRaidsByGameVersion::run())
                ->filter(fn (array $group): bool => $group['raids']->isNotEmpty())
                ->map(fn (array $group): array => [
                    'label' => $group['game_version']?->title ?? 'No game version',
                    'options' => $group['raids']
                        ->map(fn (Raid $raid): array => [
                            'value' => $raid->id,
                            'label' => "{$raid->name} ({$raid->difficulty})",
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }
}
