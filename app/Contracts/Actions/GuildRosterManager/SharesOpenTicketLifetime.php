<?php

namespace App\Contracts\Actions\GuildRosterManager;

/**
 * An action that issues or renews a GRM desktop open ticket, sharing one
 * definition of the ticket lifetime.
 */
interface SharesOpenTicketLifetime
{
    /**
     * The number of seconds an open ticket remains valid after it is issued or renewed.
     */
    public const int LIFETIME_SECONDS = 300;
}
