<?php

namespace App\Contracts\Data\GuildRosterManager;

/**
 * A GRM roster row that can be reduced to the fixed set of fields its
 * fingerprint is computed from.
 */
interface Fingerprintable
{
    /**
     * Get the fields that make up the row's fingerprint, keyed by their wire names.
     * The row's epoch is never included.
     *
     * @return array<int|string, mixed>
     */
    public function fingerprintPayload(): array;
}
