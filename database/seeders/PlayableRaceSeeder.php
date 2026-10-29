<?php

namespace Database\Seeders;

use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Data\PlayableRace\PlayableRaceData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceIndexRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceRequest;
use App\Models\GameVersion;
use App\Models\PlayableRace;
use Illuminate\Database\Seeder;

class PlayableRaceSeeder extends Seeder
{
    public function __construct(
        private BlizzardConnector $blizzard,
    ) {}

    public function run(): void
    {
        $gameVersions = GameVersion::whereNotNull('blizzard_namespace')->get();

        foreach ($gameVersions as $gameVersion) {
            /** @var array<int, LinkData> $races */
            $races = $this->blizzard->send(new GetPlayableRaceIndexRequest($gameVersion->blizzard_namespace))->dto();

            foreach ($races as $race) {
                /** @var PlayableRaceData $raceData */
                $raceData = $this->blizzard->send(new GetPlayableRaceRequest($race->id, $gameVersion->blizzard_namespace))->dto();
                $factionType = data_get($raceData, 'faction.type', 'NEUTRAL');

                $model = PlayableRace::updateOrCreate(
                    ['id' => $race->id],
                    [
                        'name' => $raceData->name,
                        'faction' => Faction::{$factionType},
                    ],
                );

                $model->gameVersions()->syncWithoutDetaching([$gameVersion->id]);

                $this->command?->line("  <info>✓</info> [{$model->id}] {$model->name} ({$gameVersion->title})");
            }
        }
    }
}
