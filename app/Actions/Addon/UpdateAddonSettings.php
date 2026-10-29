<?php

namespace App\Actions\Addon;

use App\Jobs\Addon\BuildExportFile;
use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateAddonSettings
{
    use AsAction;

    /**
     * Set the game version's loot councillors, counted ranks and counted Warcraft Logs tags.
     * Each given list flags its rows and unflags the version's other rows; absent lists are left untouched.
     * Flagging or unflagging a main councillor gives its linked characters the same flag, whether or not they are listed.
     * After commit, flushes the attendance cache when a rank changed and schedules export rebuilds
     * for the version, plus every version on its Warcraft Logs guild when a tag changed.
     *
     * @param  array{councillor_ids?: list<int>, counted_rank_ids?: list<int>, counted_tag_ids?: list<int>}  $settings
     */
    public function handle(GameVersion $gameVersion, array $settings): void
    {
        [$councillorsChanged, $ranksChanged, $tagsChanged] = DB::transaction(fn (): array => [
            array_key_exists('councillor_ids', $settings)
                && $this->syncCouncillors($gameVersion, $settings['councillor_ids']),
            array_key_exists('counted_rank_ids', $settings)
                && $this->syncFlag($gameVersion->guildRanks(), 'count_attendance', $settings['counted_rank_ids'])->isNotEmpty(),
            array_key_exists('counted_tag_ids', $settings) && $gameVersion->warcraftLogsGuild !== null
                && $this->syncFlag($gameVersion->warcraftLogsGuild->guildTags(), 'count_attendance', $settings['counted_tag_ids'])->isNotEmpty(),
        ]);

        if ($ranksChanged) {
            Cache::tags(['attendance'])->flush();
        }

        if (! $councillorsChanged && ! $ranksChanged && ! $tagsChanged) {
            return;
        }

        $gameVersionIds = $tagsChanged
            ? $gameVersion->warcraftLogsGuild->gameVersions()->pluck('id')
            : [$gameVersion->id];

        BuildExportFile::scheduleForGameVersionIds($gameVersionIds);
    }

    /**
     * Flag the listed characters as loot councillors and unflag the version's other characters,
     * then give the linked characters of every changed main that main's flag. Returns whether any character changed.
     *
     * @param  list<int|string>  $ids
     */
    protected function syncCouncillors(GameVersion $gameVersion, array $ids): bool
    {
        $changedCharacters = $this->syncFlag($gameVersion->characters(), 'is_loot_councillor', $ids);

        foreach ($changedCharacters->where('is_main', true) as $main) {
            $main->linkedCharacters()->update(['is_loot_councillor' => $main->is_loot_councillor]);
        }

        return $changedCharacters->isNotEmpty();
    }

    /**
     * Set the boolean attribute to true for the listed rows and false for the relation's other rows,
     * saving only the rows that change. Returns the rows that changed.
     *
     * @param  HasMany<Model, Model>  $rows
     * @param  list<int|string>  $ids
     * @return Collection<int, Model>
     */
    protected function syncFlag(HasMany $rows, string $attribute, array $ids): Collection
    {
        $ids = array_map(intval(...), $ids);

        $keyName = $rows->getRelated()->getQualifiedKeyName();

        $candidates = $rows->where(fn (Builder $query) => $query->where($attribute, true)->orWhereIn($keyName, $ids))->get();

        $changedRows = new Collection;

        foreach ($candidates as $row) {
            $row->{$attribute} = in_array($row->getKey(), $ids, true);

            if ($row->isDirty($attribute)) {
                $row->save();
                $changedRows->push($row);
            }
        }

        return $changedRows;
    }
}
