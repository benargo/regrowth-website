<?php

namespace App\Actions\Attendance;

use App\Data\Attendance\ReportDateRangeData;
use App\Models\GameVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Finds the earliest and latest start time of a game version's reports, cached for a day.
 */
class FindReportDateRange
{
    use AsAction;

    public function handle(GameVersion $gameVersion): ReportDateRangeData
    {
        $range = Cache::tags(['attendance', 'reports'])->remember(
            "reports:date_range:{$gameVersion->id}",
            now()->addDay(),
            fn (): array => [
                'earliest' => $gameVersion->reports()->min('start_time'),
                'latest' => $gameVersion->reports()->max('start_time'),
            ],
        );

        return new ReportDateRangeData(
            earliest: $range['earliest'] === null ? null : CarbonImmutable::parse($range['earliest']),
            latest: $range['latest'] === null ? null : CarbonImmutable::parse($range['latest']),
        );
    }
}
