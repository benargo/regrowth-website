<?php

namespace App\Data\GuildRosterManager;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * What a sync's commit changed in the roster state and the ban log.
 */
final class SyncChangesData extends Data
{
    /**
     * @param  list<string>  $members  The GUIDs of the applied member rows.
     * @param  list<string>  $leavers  The GUIDs of the applied leaver rows.
     * @param  list<string>  $alt_groups  The IDs of the applied alt group rows.
     * @param  list<string>  $departed_members  The GUIDs of the applied member removals, each now a leaver entry.
     * @param  array<string, array<string, mixed>|null>  $previous_alt_groups  Each applied alt group's stored payload before the commit, keyed by ID, or null for a new group.
     * @param  list<string>  $ban_ids  The UUIDs of the `grm_bans` rows the commit created or changed.
     */
    public function __construct(
        public readonly array $members,
        public readonly array $leavers,
        #[MapName(CamelCaseMapper::class)]
        public readonly array $alt_groups,
        #[MapName(CamelCaseMapper::class)]
        public readonly array $departed_members,
        #[MapName(CamelCaseMapper::class)]
        public readonly array $previous_alt_groups,
        #[MapName(CamelCaseMapper::class)]
        public readonly array $ban_ids,
    ) {}
}
