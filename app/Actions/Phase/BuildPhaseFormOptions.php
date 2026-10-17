<?php

namespace App\Actions\Phase;

use App\Models\GameVersion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds the select options for the phase create and edit forms.
 */
class BuildPhaseFormOptions
{
    use AsAction;

    /**
     * @return array{game_versions: list<array{value: int, label: string}>}
     */
    public function handle(): array
    {
        return [
            'game_versions' => GameVersion::orderByDesc('release_date')
                ->get()
                ->map(fn (GameVersion $gameVersion): array => [
                    'value' => $gameVersion->id,
                    'label' => $gameVersion->title,
                ])
                ->all(),
        ];
    }
}
