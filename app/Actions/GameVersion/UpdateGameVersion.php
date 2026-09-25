<?php

namespace App\Actions\GameVersion;

use App\Models\GameVersion;
use App\Models\Raid;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Applies a partial update to a game version. Only the keys present in the
 * validated data are touched: an absent key leaves its column or relationship
 * alone, and a key sent as null (or an empty list) clears it.
 *
 * Raids and bosses have no game version of their own: they follow their
 * phase, so linking a phase is all it takes to bring them along.
 *
 * Phases are saved one model at a time rather than with a bulk query update
 * so Phase still dispatches AddonSettingsProcessed. There are only tens of
 * phases, so the per-row cost doesn't matter.
 */
class UpdateGameVersion
{
    use AsAction;

    /**
     * Validated keys that describe relationships or new records, not columns.
     *
     * @var list<string>
     */
    private const array RELATIONSHIP_KEYS = [
        'playable_race_ids',
        'playable_class_ids',
        'phase_ids',
        'new_phase',
        'new_raid',
    ];

    /**
     * @param  array<string, mixed>  $data  The request's validated data.
     */
    public function handle(GameVersion $gameVersion, array $data): void
    {
        DB::transaction(function () use ($gameVersion, $data): void {
            $gameVersion->update(Arr::except($data, self::RELATIONSHIP_KEYS));

            if (Arr::exists($data, 'playable_race_ids')) {
                $gameVersion->playableRaces()->sync($data['playable_race_ids']);
            }

            if (Arr::exists($data, 'playable_class_ids')) {
                $gameVersion->playableClasses()->sync($data['playable_class_ids']);
            }

            if (Arr::exists($data, 'phase_ids')) {
                $this->assign($gameVersion, 'phases', $data['phase_ids']);
            }

            if (Arr::exists($data, 'new_phase')) {
                $gameVersion->phases()->create($data['new_phase']);
            }

            if (Arr::exists($data, 'new_raid')) {
                Raid::create($data['new_raid']);
            }
        });
    }

    /**
     * Link the given records to the game version and unlink every other record
     * it owned through the relationship. Linking a record that another game
     * version owns moves it.
     *
     * @param  'phases'  $relation
     * @param  list<int|string>  $ids
     */
    private function assign(GameVersion $gameVersion, string $relation, array $ids): void
    {
        foreach ($gameVersion->{$relation}()->whereKeyNot($ids)->get() as $record) {
            $record->update(['game_version_id' => null]);
        }

        foreach ($gameVersion->{$relation}()->getRelated()->newQuery()->whereKey($ids)->get() as $record) {
            $record->update(['game_version_id' => $gameVersion->id]);
        }
    }
}
