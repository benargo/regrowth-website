<?php

namespace App\Actions\DesktopClient;

use App\Exceptions\TooManyDesktopClientsException;
use App\Models\DesktopClient;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Binds a newly issued access token to the desktop client that requested it, creating the client at a new sign-in.
 */
class BindDesktopClientToken
{
    use AsAction;

    private const int MAX_LINKED_CLIENTS = 10;

    private const string MISMATCH_MESSAGE = 'The access token is not bound to this desktop client.';

    /**
     * Bind the access token to the device, record the client's name and point the client at the token.
     * With a previous access token ID (a refresh), the previous token must already be bound to the device.
     *
     * @throws AuthenticationException When the previous token is bound elsewhere, or the device belongs to another user.
     * @throws TooManyDesktopClientsException When the device is not one of the user's 10 linked clients.
     */
    public function handle(User $user, string $deviceId, string $deviceName, string $accessTokenId, ?string $previousAccessTokenId): DesktopClient
    {
        return DB::transaction(function () use ($user, $deviceId, $deviceName, $accessTokenId, $previousAccessTokenId): DesktopClient {
            User::whereKey($user->id)->lockForUpdate()->first();

            $desktopClient = $previousAccessTokenId === null
                ? $this->clientForSignIn($user, $deviceId)
                : $this->clientForRefresh($user, $deviceId, $previousAccessTokenId);

            $desktopClient->fill([
                'user_id' => $user->id,
                'name' => $deviceName,
                'oauth_access_token_id' => $accessTokenId,
                'last_seen_at' => now(),
            ])->save();

            $desktopClient->tokens()->create(['access_token_id' => $accessTokenId]);

            return $desktopClient;
        });
    }

    /**
     * @throws AuthenticationException
     */
    private function clientForRefresh(User $user, string $deviceId, string $previousAccessTokenId): DesktopClient
    {
        return DesktopClient::whereKey($deviceId)
            ->where('user_id', $user->id)
            ->whereRelation('tokens', 'access_token_id', $previousAccessTokenId)
            ->first() ?? throw new AuthenticationException(self::MISMATCH_MESSAGE);
    }

    /**
     * @throws AuthenticationException
     * @throws TooManyDesktopClientsException
     */
    private function clientForSignIn(User $user, string $deviceId): DesktopClient
    {
        $desktopClient = DesktopClient::lockForUpdate()->find($deviceId);

        if ($desktopClient !== null && $desktopClient->user_id !== $user->id) {
            throw new AuthenticationException(self::MISMATCH_MESSAGE);
        }

        $otherLinkedClients = DesktopClient::linked()
            ->where('user_id', $user->id)
            ->whereKeyNot($deviceId)
            ->count();

        if ($otherLinkedClients >= self::MAX_LINKED_CLIENTS) {
            throw new TooManyDesktopClientsException;
        }

        return $desktopClient ?? new DesktopClient(['id' => $deviceId]);
    }
}
