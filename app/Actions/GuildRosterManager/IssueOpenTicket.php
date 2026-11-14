<?php

namespace App\Actions\GuildRosterManager;

use App\Contracts\Actions\GuildRosterManager\SharesOpenTicketLifetime;
use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Issues an encrypted, short-lived ticket binding a user, a game version and a base revision.
 */
class IssueOpenTicket implements SharesOpenTicketLifetime
{
    use AsAction;

    public function handle(User $user, GameVersion $gameVersion, int $revision): string
    {
        return Crypt::encryptString(json_encode([
            'u' => $user->id,
            'g' => $gameVersion->id,
            'r' => $revision,
            'e' => now()->addSeconds(self::LIFETIME_SECONDS)->getTimestamp(),
        ]));
    }
}
