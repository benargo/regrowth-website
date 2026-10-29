<?php

namespace App\Actions\Attendance;

use App\Models\GameVersion;
use App\Models\Report;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Loads a game version's attendance reports, with their characters,
 * report links and zone.
 */
class LoadAttendanceReports
{
    use AsAction;

    /**
     * @param  array<int, int>  $resolvedRankIds  Ranks whose characters are loaded, unless `$withAllCharacters` is true.
     * @param  array<int, int>|null  $zoneIds  Null for every zone; an empty list for none.
     * @param  array<int, int>|null  $guildTagIds  Null for the tags that count attendance; an empty list for none.
     * @param  CarbonInterface|null  $since  Keeps reports dated after this date.
     * @param  CarbonInterface|null  $before  Keeps reports dated before this date.
     * @return EloquentCollection<int, Report>
     */
    public function handle(
        GameVersion $gameVersion,
        array $resolvedRankIds,
        ?array $zoneIds = null,
        ?array $guildTagIds = null,
        ?CarbonInterface $since = null,
        ?CarbonInterface $before = null,
        bool $withAllCharacters = false,
    ): EloquentCollection {
        return $gameVersion->reports()
            ->when(
                $guildTagIds !== null,
                fn (Builder $query) => $query->whereIn('guild_tag_id', $guildTagIds),
                fn (Builder $query) => $query->whereHas('guildTag', fn (Builder $tags) => $tags->countsAttendance()),
            )
            ->when($zoneIds !== null, fn (Builder $query) => $query->whereIn('zone_id', $zoneIds))
            ->when($since !== null, fn (Builder $query) => $query->whereDate('start_time', '>', $since))
            ->when($before !== null, fn (Builder $query) => $query->whereDate('start_time', '<', $before))
            ->with([
                'characters' => fn (BelongsToMany $characters) => $withAllCharacters
                    ? $characters
                    : $characters->whereIn('characters.rank_id', $resolvedRankIds),
                'linkedReports',
                'zone',
            ])
            ->get();
    }
}
