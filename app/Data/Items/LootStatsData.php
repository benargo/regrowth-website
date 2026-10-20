<?php

namespace App\Data\Items;

use Spatie\LaravelData\Data;

/**
 * A game version's loot counts shown on the loot hub.
 */
final class LootStatsData extends Data
{
    public function __construct(
        public readonly int $itemsCount,
        public readonly int $priorityRowsCount,
        public readonly int $commentsCount,
        public readonly int $commentersCount,
        public readonly int $reactionsCount,
    ) {}

    /**
     * Stats with every count at zero.
     */
    public static function blank(): self
    {
        return new self(
            itemsCount: 0,
            priorityRowsCount: 0,
            commentsCount: 0,
            commentersCount: 0,
            reactionsCount: 0,
        );
    }
}
