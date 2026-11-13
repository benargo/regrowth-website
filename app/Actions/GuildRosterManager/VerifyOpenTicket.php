<?php

namespace App\Actions\GuildRosterManager;

use App\Models\GameVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Verifies an open ticket against a user, a game version and a base revision, returning its expiry in the app timezone or null when invalid.
 */
class VerifyOpenTicket
{
    use AsAction;

    public function handle(?string $ticket, User $user, GameVersion $gameVersion, int $baseRevision): ?CarbonImmutable
    {
        if ($ticket === null) {
            return null;
        }

        try {
            $claims = json_decode(Crypt::decryptString($ticket), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($claims) || ! isset($claims['u'], $claims['g'], $claims['r'], $claims['e'])) {
            return null;
        }

        if ($claims['e'] < now()->getTimestamp()
            || $claims['u'] !== $user->id
            || $claims['g'] !== $gameVersion->id
            || $claims['r'] !== $baseRevision) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp($claims['e'], config('app.timezone'));
    }
}
