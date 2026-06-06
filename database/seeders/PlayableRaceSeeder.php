<?php

namespace Database\Seeders;

use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Data\PlayableRace\PlayableRaceData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceIndexRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceRequest;
use App\Models\PlayableRace;
use Illuminate\Database\Seeder;

class PlayableRaceSeeder extends Seeder
{
    public function __construct(
        private BlizzardConnector $blizzard,
    ) {}

    public function run(): void
    {
        /** @var array<int, LinkData> $races */
        $races = $this->blizzard->send(new GetPlayableRaceIndexRequest)->dto();

        foreach ($races as $race) {
            /** @var PlayableRaceData $raceData */
            $raceData = $this->blizzard->send(new GetPlayableRaceRequest($race->id))->dto();
            $factionType = data_get($raceData, 'faction.type', 'NEUTRAL');

            PlayableRace::updateOrCreate(
                ['id' => $race->id],
                [
                    'name' => $raceData->name,
                    'faction' => Faction::{$factionType},
                ],
            );

            $this->command?->line("  <info>✓</info> [{$item['id']}] {$model->name}");
        }
    }
}
