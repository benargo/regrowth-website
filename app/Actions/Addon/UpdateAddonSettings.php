<?php

namespace App\Actions\Addon;

use App\Jobs\Addon\BuildExportFile;
use App\Models\GameVersion;
use Illuminate\Database\Eloquent\Builder;
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
     * Councillor ids unflag only the version's other main characters; alt councillors keep their flag.
     * After commit, flushes the attendance cache when a rank changed and schedules export rebuilds
     * for the version, plus every version on its Warcraft Logs guild when a tag changed.
     *
     * @param  array{councillor_ids?: list<int>, counted_rank_ids?: list<int>, counted_tag_ids?: list<int>}  $settings
     */
    public function handle(GameVersion $gameVersion, array $settings): void
    {
        [$councillorsChanged, $ranksChanged, $tagsChanged] = DB::transaction(fn (): array => [
            array_key_exists('councillor_ids', $settings)
                && $this->syncFlag($gameVersion->characters(), 'is_loot_councillor', $settings['councillor_ids'], ['is_main' => true]),
            array_key_exists('counted_rank_ids', $settings)
                && $this->syncFlag($gameVersion->guildRanks(), 'count_attendance', $settings['counted_rank_ids']),
            array_key_exists('counted_tag_ids', $settings) && $gameVersion->warcraftLogsGuild !== null
                && $this->syncFlag($gameVersion->warcraftLogsGuild->guildTags(), 'count_attendance', $settings['counted_tag_ids']),
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
     * Set the boolean attribute to true for the listed rows and false for the relation's other rows
     * that match the given column values, saving only the rows that change. Returns whether any row changed.
     *
     * @param  HasMany<Model, Model>  $rows
     * @param  list<int|string>  $ids
     * @param  array<string, mixed>  $unflaggable
     */
    protected function syncFlag(HasMany $rows, string $attribute, array $ids, array $unflaggable = []): bool
    {
        $ids = array_map(intval(...), $ids);
        $changed = false;

        $keyName = $rows->getRelated()->getQualifiedKeyName();
        $isFlagged = fn (Builder $query) => $query->where($attribute, true)->where($unflaggable);

        $candidates = $rows->where(fn (Builder $query) => $query->where($isFlagged)->orWhereIn($keyName, $ids))->get();

        foreach ($candidates as $row) {
            $row->{$attribute} = in_array($row->getKey(), $ids, true);

            if ($row->isDirty($attribute)) {
                $row->save();
                $changed = true;
            }
        }

        return $changed;
    }
}
