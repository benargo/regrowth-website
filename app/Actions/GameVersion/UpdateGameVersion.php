<?php

namespace App\Actions\GameVersion;

use App\Actions\Phase\ReorderPhases;
use App\Models\GameVersion;
use Illuminate\Http\UploadedFile;
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
 * Phases are saved one model at a time, so each fires its model events; the
 * unscheduled phases' order goes through ReorderPhases.
 *
 * Guild ranks are replaced as a whole ordered list; see SyncGuildRanks.
 *
 * Images follow the same contract: an uploaded file replaces the image in
 * that collection and null removes it. They are stored once the database
 * changes have committed, so file I/O never holds the transaction open.
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
        'unscheduled_phase_ids',
        'guild_ranks',
    ];

    /**
     * @param  array<string, mixed>  $data  The request's validated data.
     */
    public function handle(GameVersion $gameVersion, array $data): void
    {
        DB::transaction(function () use ($gameVersion, $data): void {
            $gameVersion->update(Arr::except($data, [...self::RELATIONSHIP_KEYS, ...GameVersion::MEDIA_COLLECTIONS]));

            if (Arr::exists($data, 'playable_race_ids')) {
                $gameVersion->playableRaces()->sync($data['playable_race_ids']);
            }

            if (Arr::exists($data, 'playable_class_ids')) {
                $gameVersion->playableClasses()->sync($data['playable_class_ids']);
            }

            if (Arr::exists($data, 'phase_ids')) {
                $this->assign($gameVersion, 'phases', $data['phase_ids']);
            }

            if (Arr::exists($data, 'unscheduled_phase_ids')) {
                ReorderPhases::run($data['unscheduled_phase_ids']);
            }

            if (Arr::exists($data, 'guild_ranks')) {
                SyncGuildRanks::run($gameVersion, $data['guild_ranks']);
            }
        });

        foreach (Arr::only($data, GameVersion::MEDIA_COLLECTIONS) as $collection => $file) {
            $this->replaceImage($gameVersion, $collection, $file);
        }
    }

    /**
     * Store the uploaded image in its single-file collection, replacing the
     * previous one, or empty the collection when no file is given.
     */
    private function replaceImage(GameVersion $gameVersion, string $collection, ?UploadedFile $file): void
    {
        if ($file === null) {
            $gameVersion->clearMediaCollection($collection);

            return;
        }

        $gameVersion->addMedia($file)->toMediaCollection($collection);
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
