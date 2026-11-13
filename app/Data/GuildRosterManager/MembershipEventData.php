<?php

namespace App\Data\GuildRosterManager;

use Spatie\LaravelData\Data;

/**
 * A join or leave event recorded in a member's guild history.
 */
final class MembershipEventData extends Data
{
    /**
     * @param  string  $type  Either `joined` or `left`.
     * @param  string  $date  A `Y-m-d` date.
     */
    public function __construct(
        public readonly string $type,
        public readonly string $date,
    ) {}
}
