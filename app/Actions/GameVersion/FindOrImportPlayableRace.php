<?php

namespace App\Actions\GameVersion;

use App\Enums\Faction;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceRequest;
use App\Models\PlayableRace;
use Lorisleiva\Actions\Concerns\AsAction;
use Saloon\Exceptions\Request\FatalRequestException;

/**
 * Returns the playable race with the given ID, importing it and its faction
 * from Blizzard when it does not exist locally. Returns null without importing
 * when the race's faction is not one of the app's factions.
 */
class FindOrImportPlayableRace
{
    use AsAction;

    public function __construct(
        protected BlizzardConnector $blizzardConnector,
    ) {}

    /**
     * @throws BlizzardRequestException|FatalRequestException
     */
    public function handle(int $playableRaceId, ?BlizzardNamespace $namespace): ?PlayableRace
    {
        $existing = PlayableRace::find($playableRaceId);

        if ($existing !== null) {
            return $existing;
        }

        $dto = $this->blizzardConnector
            ->send(new GetPlayableRaceRequest($playableRaceId, $namespace))
            ->dto();

        $faction = Faction::tryFrom(ucfirst(strtolower((string) data_get($dto, 'faction.type'))));

        if ($faction === null) {
            return null;
        }

        return PlayableRace::updateOrCreate(['id' => $dto->id], [
            'name' => $dto->name,
            'faction' => $faction,
        ]);
    }
}
