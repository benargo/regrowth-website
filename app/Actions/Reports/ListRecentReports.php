<?php

namespace App\Actions\Reports;

use App\Models\GameVersion;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Lists a game version's five most recent reports with their linked report
 * counts, resolved and cached for ten minutes per version. Empty without a
 * version.
 */
class ListRecentReports
{
    use AsAction;

    private const int LIMIT = 5;

    /**
     * @return list<array<string, mixed>>
     */
    public function handle(?GameVersion $gameVersion, Request $request): array
    {
        if ($gameVersion === null) {
            return [];
        }

        return Cache::tags('raiding', 'reports')->remember(
            'reports:recent:'.self::LIMIT.":{$gameVersion->id}",
            now()->addMinutes(10),
            fn (): array => Report::withCount('linkedReports')
                ->where('game_version_id', $gameVersion->id)
                ->orderBy('start_time', 'desc')
                ->take(self::LIMIT)
                ->get()
                ->toResourceCollection()
                ->resolve($request),
        );
    }
}
