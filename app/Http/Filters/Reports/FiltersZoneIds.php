<?php

namespace App\Http\Filters\Reports;

use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\Filters\Filter;

class FiltersZoneIds implements Filter
{
    public function __invoke(Builder $query, mixed $value, string $property): void
    {
        $ids = collect(is_array($value) ? $value : [$value])
            ->map(fn ($v) => (int) $v)
            ->all();

        $wantsUnzoned = in_array(0, $ids, true);
        $zoneIds = array_values(array_filter($ids, fn ($id) => $id !== 0));

        $query->where(function (Builder $inner) use ($wantsUnzoned, $zoneIds) {
            if ($wantsUnzoned) {
                $inner->whereNull('zone_id');
            }
            if (! empty($zoneIds)) {
                $inner->orWhereIn('zone_id', $zoneIds);
            }
        });
    }
}
