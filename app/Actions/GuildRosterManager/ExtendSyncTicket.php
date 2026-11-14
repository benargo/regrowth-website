<?php

namespace App\Actions\GuildRosterManager;

use App\Contracts\Actions\GuildRosterManager\SharesOpenTicketLifetime;
use App\Models\GuildRosterManager\Upload;
use Carbon\CarbonImmutable;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Renews a staging sync's open ticket for the ticket lifetime, ending no later than the sync's expiry.
 */
class ExtendSyncTicket implements SharesOpenTicketLifetime
{
    use AsAction;

    public function handle(Upload $sync): CarbonImmutable
    {
        $renewed = now()->addSeconds(self::LIFETIME_SECONDS);
        $ticketExpiresAt = $sync->expires_at !== null && $sync->expires_at->lt($renewed) ? $sync->expires_at : $renewed;

        $sync->forceFill(['ticket_expires_at' => $ticketExpiresAt])->save();

        return $ticketExpiresAt->toImmutable();
    }
}
