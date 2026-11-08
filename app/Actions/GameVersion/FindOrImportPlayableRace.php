<?php

namespace App\Actions\GameVersion;

use App\Enums\Faction;
use App\Enums\Gender;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Requests\PlayableRace\GetPlayableRaceRequest;
use App\Jobs\AttachShadowAvatarToPlayableRace;
use App\Models\PlayableRace;
use Lorisleiva\Actions\Concerns\AsAction;
use Saloon\Exceptions\Request\FatalRequestException;

/**
 * Returns the playable race with the given ID, importing it and its faction
 * from Blizzard when it does not exist locally, and queues a fetch of each of
 * its shadow avatars not yet stored, skipping any the render CDN
 * was recently found not to have. Returns null without importing when the
 * race's faction is not one of the app's factions.
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
        $playableRace = PlayableRace::find($playableRaceId) ?? $this->import($playableRaceId, $namespace);

        if ($playableRace === null) {
            return null;
        }

        $this->queueMissingShadowAvatars($playableRace);

        return $playableRace;
    }

    /**
     * @throws BlizzardRequestException|FatalRequestException
     */
    private function import(int $playableRaceId, ?BlizzardNamespace $namespace): ?PlayableRace
    {
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

    private function queueMissingShadowAvatars(PlayableRace $playableRace): void
    {
        if ($playableRace->hasAllShadowAvatars()) {
            return;
        }

        foreach (Gender::cases() as $gender) {
            if (! $playableRace->hasShadowAvatar($gender) && ! $playableRace->isShadowAvatarKnownMissing($gender)) {
                AttachShadowAvatarToPlayableRace::dispatch($playableRace->id, $gender);
            }
        }
    }
}
