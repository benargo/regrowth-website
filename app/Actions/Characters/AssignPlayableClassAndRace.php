<?php

namespace App\Actions\Characters;

use App\Actions\GameVersion\FindOrImportPlayableClass;
use App\Actions\GameVersion\FindOrImportPlayableRace;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Models\Character;
use App\Models\GameVersion;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Saloon\Exceptions\Request\FatalRequestException;

/**
 * Sets the playable class and race on a character (without saving it). A class or
 * race not yet linked to the game version is linked, and imported from Blizzard
 * when missing. When an import fails the character keeps its previous value and a
 * warning is logged. A race outside the app's factions is skipped the same way,
 * without a warning. An instance does not retry an ID that has already failed or
 * been skipped, so one instance should serve a whole sync.
 */
class AssignPlayableClassAndRace
{
    use AsAction;

    /**
     * @var array<int, int>
     */
    private array $failedClassIds = [];

    /**
     * @var array<int, int>
     */
    private array $failedRaceIds = [];

    public function handle(Character $character, GameVersion $gameVersion, int $playableClassId, int $playableRaceId): void
    {
        $this->assignClass($character, $gameVersion, $playableClassId);
        $this->assignRace($character, $gameVersion, $playableRaceId);
    }

    private function assignClass(Character $character, GameVersion $gameVersion, int $playableClassId): void
    {
        if ($gameVersion->playableClasses->contains($playableClassId)) {
            $character->playable_class_id = $playableClassId;

            return;
        }

        if (in_array($playableClassId, $this->failedClassIds, true)) {
            return;
        }

        try {
            $playableClass = FindOrImportPlayableClass::run($playableClassId, $gameVersion->blizzard_namespace);
        } catch (BlizzardRequestException|FatalRequestException $exception) {
            Log::warning('Failed to import playable class from Blizzard.', [
                'character_id' => $character->id,
                'playable_class_id' => $playableClassId,
                'error' => $exception->getMessage(),
            ]);

            $this->failedClassIds[] = $playableClassId;

            return;
        }

        $character->playable_class_id = $playableClass->id;
        $gameVersion->playableClasses()->syncWithoutDetaching([$playableClass->id]);
        $gameVersion->unsetRelation('playableClasses');
    }

    private function assignRace(Character $character, GameVersion $gameVersion, int $playableRaceId): void
    {
        if ($gameVersion->playableRaces->contains($playableRaceId)) {
            $character->playable_race_id = $playableRaceId;

            return;
        }

        if (in_array($playableRaceId, $this->failedRaceIds, true)) {
            return;
        }

        try {
            $playableRace = FindOrImportPlayableRace::run($playableRaceId, $gameVersion->blizzard_namespace);
        } catch (BlizzardRequestException|FatalRequestException $exception) {
            Log::warning('Failed to import playable race from Blizzard.', [
                'character_id' => $character->id,
                'playable_race_id' => $playableRaceId,
                'error' => $exception->getMessage(),
            ]);

            $this->failedRaceIds[] = $playableRaceId;

            return;
        }

        if ($playableRace === null) {
            $this->failedRaceIds[] = $playableRaceId;

            return;
        }

        $character->playable_race_id = $playableRace->id;
        $gameVersion->playableRaces()->syncWithoutDetaching([$playableRace->id]);
        $gameVersion->unsetRelation('playableRaces');
    }
}
