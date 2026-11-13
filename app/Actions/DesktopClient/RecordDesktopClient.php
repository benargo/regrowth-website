<?php

namespace App\Actions\DesktopClient;

use App\Models\DesktopClient;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Records a request from a user's existing Regrowth Desktop installation, updating its name, access token and last seen time.
 */
class RecordDesktopClient
{
    use AsAction;

    /**
     * @throws ModelNotFoundException When no desktop client has the ID.
     * @throws AuthorizationException When the ID belongs to a different user.
     */
    public function handle(User $user, string $clientId, string $name, ?string $accessTokenId): DesktopClient
    {
        $desktopClient = DesktopClient::findOrFail($clientId);

        if ($desktopClient->user_id !== $user->id) {
            throw new AuthorizationException('This desktop client belongs to another user.');
        }

        $desktopClient->update([
            'name' => $name,
            'oauth_access_token_id' => $accessTokenId,
            'last_seen_at' => now(),
        ]);

        return $desktopClient;
    }
}
