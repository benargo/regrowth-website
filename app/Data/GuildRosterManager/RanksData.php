<?php

namespace App\Data\GuildRosterManager;

use App\Contracts\Data\GuildRosterManager\Fingerprintable;
use Spatie\LaravelData\Data;

/**
 * The guild's rank names, ordered by rank index.
 */
final class RanksData extends Data implements Fingerprintable
{
    /**
     * @param  array<int, string>  $ranks
     */
    public function __construct(
        public readonly array $ranks,
    ) {}

    /**
     * Get the bare list of rank names the fingerprint is computed from.
     *
     * @return array<int, string>
     */
    public function fingerprintPayload(): array
    {
        return array_values($this->ranks);
    }
}
