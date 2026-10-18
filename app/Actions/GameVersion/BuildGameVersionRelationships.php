<?php

namespace App\Actions\GameVersion;

use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds the data the game version Edit and Setup pages use to link playable
 * races, playable classes and phases to a version, and to list its guild ranks.
 */
class BuildGameVersionRelationships
{
    use AsAction;

    /**
     * @return array{
     *     playable_races: ResourceCollection,
     *     playable_classes: ResourceCollection,
     *     phases: ResourceCollection,
     *     unscheduled_phases: ResourceCollection,
     *     guild_ranks: ResourceCollection,
     * }
     */
    public function handle(GameVersion $gameVersion): array
    {
        $phases = Phase::with(['gameVersion', 'raids' => fn ($query) => $query->orderBy('name'), 'raids.media'])
            ->inReleaseOrder()
            ->get();
        $ownPhases = $phases->where('game_version_id', $gameVersion->id);

        return [
            'playable_races' => PlayableRace::orderBy('name')->get()
                ->toResourceCollection()
                ->additional([
                    'selected_ids' => $gameVersion->playableRaces()->pluck('playable_races.id')->all(),
                ]),
            'playable_classes' => PlayableClass::with('media')->orderBy('name')->get()
                ->toResourceCollection()
                ->additional([
                    'selected_ids' => $gameVersion->playableClasses()->pluck('playable_classes.id')->all(),
                ]),
            'phases' => $phases->toResourceCollection()
                ->additional([
                    'selected_ids' => $ownPhases->pluck('id')->values()->all(),
                ]),
            'unscheduled_phases' => $ownPhases->whereNull('start_date')
                ->values()
                ->toResourceCollection(),
            'guild_ranks' => $gameVersion->guildRanks()
                ->withCount('characters')
                ->ordered()
                ->get()
                ->toResourceCollection(),
        ];
    }
}
