<?php

namespace App\Http\Filters\Reports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\Filters\Filter;

class FiltersDaysOfWeek implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $days = collect(is_array($value) ? $value : [$value])
            ->map(fn ($v) => (int) $v)
            ->all();

        if (DB::connection()->getDriverName() === 'sqlite') {
            $query->whereIn(DB::raw("CAST(strftime('%w', datetime(start_time)) AS INTEGER)"), $days);
        } else {
            $query->whereIn(DB::raw('DAYOFWEEK(start_time)'), array_map(fn ($d) => $d + 1, $days));
        }
    }
}
