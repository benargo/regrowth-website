<?php

namespace App\Data\Navigation;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

final class NavigationData extends Data
{
    /**
     * @param  array<int, LatestDiscussionData>  $latestDiscussions
     */
    public function __construct(
        public readonly ?NextRaidData $nextRaid,
        #[DataCollectionOf(LatestDiscussionData::class)]
        public readonly array $latestDiscussions,
    ) {}
}
