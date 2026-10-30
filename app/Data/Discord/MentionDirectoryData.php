<?php

namespace App\Data\Discord;

use Spatie\LaravelData\Data;

/**
 * The stored Discord channels, roles and emoji available for mention tags.
 */
final class MentionDirectoryData extends Data
{
    /**
     * @param  array<int, MentionChannelData>  $channels
     * @param  array<int, MentionRoleData>  $roles
     * @param  array<int, MentionEmojiData>  $emoji
     */
    public function __construct(
        public readonly array $channels,
        public readonly array $roles,
        public readonly array $emoji,
    ) {}
}
