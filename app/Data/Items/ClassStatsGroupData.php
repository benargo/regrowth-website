<?php

namespace App\Data\Items;

use App\Models\PlayableClass;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * A playable class's group of priority rows in the highest-priority stats table.
 */
final class ClassStatsGroupData extends Data
{
    /**
     * @param  array<int, int>  $counts  tier-1 item counts keyed by phase id
     * @param  Collection<int, PriorityStatsRowData>  $children
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly ?string $icon,
        public readonly array $counts,
        public readonly int $total,
        public readonly Collection $children,
        public readonly string $kind = 'class',
    ) {}

    /**
     * Build the group for a playable class.
     *
     * @param  array<int, int>  $counts  tier-1 item counts keyed by phase id
     * @param  Collection<int, PriorityStatsRowData>  $children
     */
    public static function fromPlayableClass(PlayableClass $playableClass, array $counts, Collection $children): self
    {
        return new self(
            id: $playableClass->id,
            title: $playableClass->name,
            slug: $playableClass->slug,
            icon: $playableClass->getFirstMediaUrl('blizzard_icons') ?: null,
            counts: $counts,
            total: array_sum($counts),
            children: $children,
        );
    }
}
