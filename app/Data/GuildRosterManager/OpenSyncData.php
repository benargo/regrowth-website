<?php

namespace App\Data\GuildRosterManager;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * The body of a request to open a desktop sync.
 */
final class OpenSyncData extends Data
{
    /**
     * @param  string  $file_hash  The SHA-256 of the SavedVariables file, in lowercase hex.
     */
    public function __construct(
        #[MapName(CamelCaseMapper::class)]
        public readonly string $game_version,
        #[MapName(CamelCaseMapper::class)]
        public readonly string $guild_key,
        #[MapName(CamelCaseMapper::class)]
        public readonly string $addon_version,
        #[MapName(CamelCaseMapper::class)]
        public readonly int $base_revision,
        #[MapName(CamelCaseMapper::class)]
        public readonly ?string $open_ticket,
        #[MapName(CamelCaseMapper::class)]
        public readonly string $file_hash,
    ) {}
}
