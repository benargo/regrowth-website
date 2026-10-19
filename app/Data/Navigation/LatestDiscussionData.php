<?php

namespace App\Data\Navigation;

use App\Models\Comment;
use App\Models\Item;
use Spatie\LaravelData\Data;

final class LatestDiscussionData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $itemName,
        public readonly string $itemQualityClass,
        public readonly ?string $itemIcon,
        public readonly string $url,
        public readonly ?string $bossName,
        public readonly string $postedAgo,
        public readonly int $repliesCount,
    ) {}

    /**
     * Describes a root comment on an item, with its replies_count loaded and the
     * item's first `blizzard_icons` URL, or null when it has none.
     */
    public static function fromComment(Comment $comment): self
    {
        /** @var Item $item */
        $item = $comment->commentable;

        return new self(
            id: $comment->id,
            itemName: $item->name,
            itemQualityClass: $item->quality->cssClass('text'),
            itemIcon: $item->getFirstMediaUrl('blizzard_icons') ?: null,
            url: route('loot.items.show', ['item' => $item, 'slug' => $item->slug]),
            bossName: $item->boss?->name,
            postedAgo: $comment->created_at->diffForHumans(),
            repliesCount: $comment->replies_count,
        );
    }
}
