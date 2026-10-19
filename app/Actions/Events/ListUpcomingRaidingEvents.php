<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\GameVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lists a game version's live events ending between two hours ago and the
 * end of next week, soonest first, resolved for the viewer and cached for
 * ten minutes per version and viewer type. Empty without a version.
 */
class ListUpcomingRaidingEvents
{
    use AsAction;

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(?GameVersion $gameVersion, Request $request): array
    {
        if ($gameVersion === null) {
            return [];
        }

        return Cache::tags('raiding', 'events')->remember(
            ($request->user() ? 'events:upcoming' : 'events:upcoming:guests').":{$gameVersion->id}",
            now()->addMinutes(10),
            fn (): array => BuildEventsForViewer::run(
                Event::live()->where('game_version_id', $gameVersion->id)
                    ->where('end_time', '>=', now()->subHours(2))
                    ->where('end_time', '<=', now()->addWeek()->endOfDay())
                    ->orderBy('start_time'),
                $request,
            )->resolve($request),
        );
    }
}
