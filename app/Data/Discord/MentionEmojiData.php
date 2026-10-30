<?php

namespace App\Data\Discord;

use Spatie\LaravelData\Data;

/**
 * A stored Discord emoji offered for `<:name:id>` and `<a:name:id>` tags.
 */
final class MentionEmojiData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly bool $animated,
        public readonly bool $available,
        public readonly string $url,
    ) {}
}
