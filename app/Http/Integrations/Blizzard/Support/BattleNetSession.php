<?php

namespace App\Http\Integrations\Blizzard\Support;

use App\Http\Integrations\Blizzard\Exceptions\ApiException;
use App\Http\Integrations\Blizzard\Exceptions\BattleNetSessionException;
use Closure;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Auth\AccessTokenAuthenticator;

/**
 * The signed-in user's Battle.net connection, held in their session: the OAuth
 * state, the user's access token, and where to send them after connecting.
 */
class BattleNetSession
{
    /**
     * The OAuth state sent to Battle.net, checked on the callback.
     */
    public const string STATE_KEY = 'battlenet.oauth_state';

    /**
     * The user's Saloon `AccessTokenAuthenticator`, which carries its own expiry.
     */
    public const string AUTHENTICATOR_KEY = 'battlenet.authenticator';

    /**
     * Where the callback sends the user. Only ever written by the server.
     */
    public const string INTENDED_URL_KEY = 'battlenet.intended_url';

    public function __construct(protected Session $session) {}

    /**
     * Generate and remember the OAuth state for a new authorization.
     */
    public function startAuthorization(): string
    {
        $state = Str::random(32);

        $this->session->put(self::STATE_KEY, $state);

        return $state;
    }

    /**
     * Check the callback's state against the one remembered, which can only be
     * used once.
     */
    public function verifyState(mixed $state): bool
    {
        $expectedState = $this->session->pull(self::STATE_KEY);

        return is_string($expectedState) && $expectedState !== ''
            && is_string($state) && hash_equals($expectedState, $state);
    }

    public function store(AccessTokenAuthenticator $authenticator): void
    {
        $this->session->put(self::AUTHENTICATOR_KEY, $authenticator);
    }

    /**
     * The user's access token, while it is still valid.
     */
    public function accessToken(): ?string
    {
        $authenticator = $this->session->get(self::AUTHENTICATOR_KEY);

        return $authenticator?->hasNotExpired() ? $authenticator->getAccessToken() : null;
    }

    public function isConnected(): bool
    {
        return $this->accessToken() !== null;
    }

    /**
     * Run a Battle.net request with the user's access token, forgetting the
     * token if Battle.net rejects it.
     *
     * @template TResult
     *
     * @param  Closure(string): TResult  $callback
     * @return TResult
     *
     * @throws BattleNetSessionException when the user must reconnect or Battle.net is unavailable.
     */
    public function withAccessToken(Closure $callback): mixed
    {
        $accessToken = $this->accessToken() ?? throw new BattleNetSessionException(BattleNetSessionError::Reconnect);

        try {
            return $callback($accessToken);
        } catch (ApiException $exception) {
            if ($exception->getBlizzardStatus() === 401) {
                $this->session->forget(self::AUTHENTICATOR_KEY);

                throw new BattleNetSessionException(BattleNetSessionError::Reconnect, $exception);
            }

            Log::warning('Battle.net request failed.', ['status' => $exception->getBlizzardStatus()]);

            throw new BattleNetSessionException(BattleNetSessionError::Unavailable, $exception);
        } catch (FatalRequestException $exception) {
            Log::warning('Battle.net request failed.', ['message' => $exception->getMessage()]);

            throw new BattleNetSessionException(BattleNetSessionError::Unavailable, $exception);
        }
    }

    public function rememberIntendedUrl(string $url): void
    {
        $this->session->put(self::INTENDED_URL_KEY, $url);
    }

    public function pullIntendedUrl(): ?string
    {
        return $this->session->pull(self::INTENDED_URL_KEY);
    }
}
