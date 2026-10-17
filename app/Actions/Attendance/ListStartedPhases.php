<?php

namespace App\Actions\Attendance;

use App\Models\GameVersion;
use App\Models\Phase;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lists the IDs and start dates of a game version's started phases, newest first, cached until the end of the day.
 */
class ListStartedPhases
{
    use AsAction;

    /**
     * @return EloquentCollection<int, Phase>
     */
    public function handle(GameVersion $gameVersion): EloquentCollection
    {
        return Phase::hydrate(Cache::tags(['attendance'])->remember(
            "phases:where_started:{$gameVersion->id}",
            now()->endOfDay(),
            fn (): array => $gameVersion->phases()->started()->orderByDesc('start_date')->get(['id', 'start_date'])
                ->map(fn (Phase $phase): array => $phase->getAttributes())
                ->all(),
        ));
    }
}
