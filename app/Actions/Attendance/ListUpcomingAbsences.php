<?php

namespace App\Actions\Attendance;

use App\Models\GameVersion;
use App\Models\PlannedAbsence;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lists the planned absences, starting today or later, of a game version's characters in start order,
 * cached until the end of the day, with each absence's character and playable class loaded.
 */
class ListUpcomingAbsences
{
    use AsAction;

    /**
     * @return EloquentCollection<int, PlannedAbsence>
     */
    public function handle(GameVersion $gameVersion): EloquentCollection
    {
        return PlannedAbsence::hydrate(Cache::tags(['attendance'])->remember(
            "planned_absences:upcoming:{$gameVersion->id}",
            now()->endOfDay(),
            fn (): array => PlannedAbsence::upcoming()
                ->whereRelation('character', 'game_version_id', $gameVersion->id)
                ->get()
                ->map(fn (PlannedAbsence $plannedAbsence): array => $plannedAbsence->getAttributes())
                ->all(),
        ))->load('character.playableClass');
    }
}
