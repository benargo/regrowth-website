<?php

namespace App\Data\Items;

use Illuminate\Support\Collection;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

/**
 * The highest-priority stats table of role rows and playable class groups.
 */
final class HighestPriorityStatsTableData extends Data
{
    /**
     * @param  Collection<int, PriorityStatsRowData>  $roleRows
     * @param  Collection<int, ClassStatsGroupData>  $classGroups
     */
    public function __construct(
        #[DataCollectionOf(PriorityStatsRowData::class)]
        public readonly Collection $roleRows,
        #[DataCollectionOf(ClassStatsGroupData::class)]
        public readonly Collection $classGroups,
    ) {}
}
