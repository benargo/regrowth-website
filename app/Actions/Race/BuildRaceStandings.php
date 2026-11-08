<?php

namespace App\Actions\Race;

use App\Contracts\Race\RaceToSixty;
use App\Enums\RaceState;
use App\Http\Resources\CharacterResource;
use App\Http\Resources\RacePlacementResource;
use App\Http\Resources\ScopedResourceCollection;
use App\Models\GameVersion;
use App\Models\RacePlacement;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds the Race to Level 60 standings of a game version: the race state,
 * the leader, the pack chasing the leader, every racer and the placements.
 */
class BuildRaceStandings implements RaceToSixty
{
    use AsAction;

    /**
     * @return array{state: RaceState, target_level: int, checked_at: ?string, closes_at: ?string, leader: ?CharacterResource, pack: ScopedResourceCollection, racers?: ScopedResourceCollection, placements: AnonymousResourceCollection}
     */
    public function handle(GameVersion $gameVersion, bool $withRacers = true): array
    {
        $pack = $gameVersion->racePack(self::PACK_WINDOW)
            ->with(['media', 'playableClass.media', 'playableRace.media'])
            ->orderByDesc('level')
            ->orderBy('name')
            ->get();

        $leader = $pack->first();

        $placements = $gameVersion->racePlacements()
            ->with('character.playableClass.media')
            ->orderBy('position')
            ->get()
            ->sortBy([['position', 'asc'], [fn (RacePlacement $a, RacePlacement $b): int => strcmp($a->character->name, $b->character->name)]])
            ->values();

        $bronzeReachedAt = $placements->firstWhere('position', 3)?->reached_at;

        $standings = [
            'state' => RaceState::fromBronzeReachedAt($bronzeReachedAt),
            'target_level' => self::TARGET_LEVEL,
            'checked_at' => Cache::get(self::CHECKED_AT_CACHE_KEY),
            'closes_at' => $bronzeReachedAt?->copy()->addHours(self::HONOURABLE_MENTION_HOURS)->toIso8601String(),
            'leader' => $leader?->toResource()->scope('race'),
            'pack' => CharacterResource::collection($pack)->scope('race'),
            'placements' => RacePlacementResource::collection($placements),
        ];

        if ($withRacers) {
            $standings['racers'] = CharacterResource::collection(
                $gameVersion->characters()
                    ->with(['media', 'playableClass.media', 'playableRace.media'])
                    ->orderByDesc('level')
                    ->orderBy('name')
                    ->get(),
            )->scope('race');
        }

        return $standings;
    }
}
