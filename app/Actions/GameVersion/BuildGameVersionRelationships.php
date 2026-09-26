<?php

namespace App\Actions\GameVersion;

use App\Http\Resources\PlayableClassResource;
use App\Http\Resources\PlayableRaceResource;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\PlayableClass;
use App\Models\PlayableRace;
use App\Models\Raid;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Builds the checklist data the game version Edit and Setup pages use to link
 * records to a version: every candidate record, which of them this version
 * links, and, for has-many records, which version (if any) owns each one now.
 *
 * Raids have no game_version_id of their own, so they are nested read-only
 * under the phase option that owns them rather than returned as their own
 * selectable list.
 */
class BuildGameVersionRelationships
{
    use AsAction;

    /**
     * @return array{
     *     playable_races: array{options: list<array<string, mixed>>, selected_ids: list<int>},
     *     playable_classes: array{options: list<array<string, mixed>>, selected_ids: list<int>},
     *     phases: array{options: list<array{id: int, label: string, description: string, game_version: array{id: int, title: string}|null, raids: list<array{id: int, name: string, difficulty: string, color: string|null}>}>, selected_ids: list<int>}
     * }
     */
    public function handle(GameVersion $gameVersion): array
    {
        $phases = Phase::query()->with('gameVersion:id,title')->orderBy('number')->get();

        $raidsByPhase = Raid::query()->orderBy('name')->get()->groupBy('phase_id');

        return [
            'playable_races' => [
                'options' => PlayableRaceResource::collection(PlayableRace::query()->orderBy('name')->get())->resolve(),
                'selected_ids' => $gameVersion->playableRaces()->pluck('playable_races.id')->all(),
            ],
            'playable_classes' => [
                'options' => PlayableClassResource::collection(PlayableClass::query()->with('media')->orderBy('name')->get())->resolve(),
                'selected_ids' => $gameVersion->playableClasses()->pluck('playable_classes.id')->all(),
            ],
            'phases' => [
                'options' => $phases->map(fn (Phase $phase): array => [
                    'id' => $phase->id,
                    'label' => "Phase {$phase->number}",
                    'description' => $phase->description,
                    'game_version' => $this->owner($phase->gameVersion),
                    'raids' => $raidsByPhase->get($phase->id, collect())->map(fn (Raid $raid): array => [
                        'id' => $raid->id,
                        'name' => $raid->name,
                        'difficulty' => $raid->difficulty,
                        'color' => $raid->color,
                    ])->values()->all(),
                ])->all(),
                'selected_ids' => $phases->where('game_version_id', $gameVersion->id)->pluck('id')->all(),
            ],
        ];
    }

    /**
     * @return array{id: int, title: string}|null
     */
    private function owner(?GameVersion $owner): ?array
    {
        return $owner ? ['id' => $owner->id, 'title' => $owner->title] : null;
    }
}
