<?php

namespace App\Data\Discord;

use Spatie\LaravelData\Attributes\Validation\Regex;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

/**
 * A stored Discord channel offered for `<#id>` tags.
 */
final class MentionChannelData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $category,
        public readonly bool $isCategory,
        public readonly bool $isDeleted,
        /** @var string Link to the channel on discord.com, with the thread ID after the parent channel ID for threads */
        #[StringType, Regex('#^https://discord\.com/channels/\d+/\d+(/\d+)?$#')]
        public readonly string $url,
    ) {}
}
