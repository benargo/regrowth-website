<?php

namespace App\Data\GuildRosterManager;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * A game version's slug and current roster revision with the fingerprint of every row the user may see.
 */
final class RosterStateData extends Data
{
    /**
     * @param  array{members: array<string, string>, leavers: array<string, string>, altGroups: array<string, string>, bans?: array<string, string>, ranks: ?string}  $fingerprints
     * @param  array{create: bool, update: bool}  $ban_access
     */
    public function __construct(
        public readonly string $slug,
        public readonly int $revision,
        #[MapName(CamelCaseMapper::class)]
        public readonly ?Carbon $committed_at,
        public readonly array $fingerprints,
        #[MapName(CamelCaseMapper::class)]
        public readonly array $ban_access,
    ) {}
}
