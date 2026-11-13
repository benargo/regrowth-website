<?php

namespace App\Data\GuildRosterManager;

use App\Contracts\Data\GuildRosterManager\RosterEntryData;
use Spatie\LaravelData\Data;

/**
 * A group of linked characters, the name of its main and its members' GUIDs.
 */
final class AltGroupData extends Data implements RosterEntryData
{
    /**
     * @param  array<int, string>  $members  Member GUIDs in GRM order.
     * @param  int  $epoch  GRM's `timeModified` for the group.
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $main,
        public readonly array $members,
        public readonly int $epoch = 0,
    ) {}

    /**
     * Get the alt group's ID.
     */
    public function key(): string
    {
        return $this->id;
    }

    /**
     * Get the fields the alt group's fingerprint is computed from.
     *
     * @return array<string, mixed>
     */
    public function fingerprintPayload(): array
    {
        return [
            'id' => $this->id,
            'main' => $this->main,
            'members' => $this->members,
        ];
    }
}
