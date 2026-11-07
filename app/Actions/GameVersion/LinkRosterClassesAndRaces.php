<?php

namespace App\Actions\GameVersion;

use App\Http\Integrations\Blizzard\Data\Guild\GuildRosterMemberData;
use App\Models\GameVersion;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\LaravelData\Optional;
use Throwable;

/**
 * Links every distinct playable class and race found on the given roster members
 * to the game version, importing any that are missing from Blizzard. A failed
 * import is logged as a warning and does not stop the remaining IDs. A race
 * outside the app's factions is left unlinked.
 */
class LinkRosterClassesAndRaces
{
    use AsAction;

    /**
     * @param  array<int, GuildRosterMemberData>  $members
     */
    public function handle(GameVersion $gameVersion, array $members): void
    {
        $classIds = collect($members)
            ->pluck('character.playableClass.id')
            ->reject(fn (Optional|int $id): bool => $id instanceof Optional)
            ->unique();

        $raceIds = collect($members)
            ->pluck('character.playableRace.id')
            ->reject(fn (Optional|int $id): bool => $id instanceof Optional)
            ->unique();

        foreach ($classIds as $classId) {
            try {
                $playableClass = FindOrImportPlayableClass::run($classId, $gameVersion->blizzard_namespace);
                $gameVersion->playableClasses()->syncWithoutDetaching([$playableClass->id]);
            } catch (Throwable $exception) {
                Log::warning('Failed to import playable class from Blizzard.', [
                    'game_version_id' => $gameVersion->id,
                    'playable_class_id' => $classId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        foreach ($raceIds as $raceId) {
            try {
                $playableRace = FindOrImportPlayableRace::run($raceId, $gameVersion->blizzard_namespace);

                if ($playableRace !== null) {
                    $gameVersion->playableRaces()->syncWithoutDetaching([$playableRace->id]);
                }
            } catch (Throwable $exception) {
                Log::warning('Failed to import playable race from Blizzard.', [
                    'game_version_id' => $gameVersion->id,
                    'playable_race_id' => $raceId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }
}
