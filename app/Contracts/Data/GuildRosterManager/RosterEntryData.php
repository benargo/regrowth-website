<?php

namespace App\Contracts\Data\GuildRosterManager;

use Illuminate\Contracts\Support\Arrayable;

/**
 * A GRM roster row of one kind (member, leaver, alt group or ban), identified by its key.
 *
 * @extends Arrayable<string, mixed>
 */
interface RosterEntryData extends Arrayable, Fingerprintable
{
    /**
     * The GRM epoch timestamp of the row.
     */
    public int $epoch { get; }

    /**
     * Get the value that identifies the row within its kind: the character GUID, or the alt group ID.
     */
    public function key(): string;
}
