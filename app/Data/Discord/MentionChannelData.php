<?php

namespace App\Data\Discord;

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
    ) {}
}
