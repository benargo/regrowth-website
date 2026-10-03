<?php

namespace App\Http\Integrations\WarcraftLogs\Data\GuildTags;

use Spatie\LaravelData\Data;

final class GuildTagData extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
    ) {}
}
