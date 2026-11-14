<?php

namespace App\Data\GuildRosterManager;

use App\Enums\GuildRosterManager\RosterEntryKind;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * The keys a sync's commit lists as absent from the file, per kind.
 */
final class RosterRemovalsData extends Data
{
    /**
     * @param  array<int, string>  $members  Member GUIDs.
     * @param  array<int, string>  $leavers  Leaver GUIDs.
     * @param  array<int, string>  $alt_groups  Alt group IDs.
     * @param  array<int, string>  $bans  Ban GUIDs.
     */
    public function __construct(
        public readonly array $members = [],
        public readonly array $leavers = [],
        #[MapName(CamelCaseMapper::class)]
        public readonly array $alt_groups = [],
        public readonly array $bans = [],
    ) {}

    /**
     * Get the removed keys of the given kind.
     *
     * @return array<int, string>
     */
    public function keysFor(RosterEntryKind $kind): array
    {
        return match ($kind) {
            RosterEntryKind::Member => $this->members,
            RosterEntryKind::Leaver => $this->leavers,
            RosterEntryKind::AltGroup => $this->alt_groups,
            RosterEntryKind::Ban => $this->bans,
        };
    }
}
