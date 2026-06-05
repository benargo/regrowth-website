<?php

namespace Database\Seeders;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceIndexRequest;
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
            PlayableRace::updateOrCreate(
                ['id' => $race->id],
                ['name' => $race->name],
            );
        }
    }
}
