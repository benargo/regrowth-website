<?php

namespace App\Data\Items;

use App\Enums\LootPriorityType;
use App\Models\LootPriority;
use Spatie\LaravelData\Data;

/**
 * A loot priority's row in the highest-priority stats table.
 */
final class PriorityStatsRowData extends Data
{
    /**
     * @param  array<int, int>  $counts  tier-1 item counts keyed by phase id
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly LootPriorityType $type,
        public readonly string $icon,
        public readonly array $counts,
        public readonly int $total,
        public readonly string $kind = 'priority',
    ) {}

    /**
     * Build the row for a loot priority.
     *
     * @param  array<int, int>  $counts  tier-1 item counts keyed by phase id
     */
    public static function fromPriority(LootPriority $priority, array $counts): self
    {
        return new self(
            id: $priority->id,
            title: $priority->title,
            type: $priority->type,
            icon: $priority->getFirstMediaUrl('blizzard_icons'),
            counts: $counts,
            total: array_sum($counts),
        );
    }
}
