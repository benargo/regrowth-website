<?php

namespace App\Data\Discord;

use Spatie\LaravelData\Data;

/**
 * A stored Discord role offered for `<@&id>` tags.
 */
final class MentionRoleData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}
}
