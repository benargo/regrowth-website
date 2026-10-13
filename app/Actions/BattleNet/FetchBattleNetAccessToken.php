<?php

namespace App\Actions\BattleNet;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\OAuth\GetAuthorizationCodeTokenRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Auth\AccessTokenAuthenticator;
use SensitiveParameter;

/**
 * Exchange a Battle.net authorization code for the user's access token.
 */
class FetchBattleNetAccessToken
{
    use AsAction;

    public function __construct(protected BlizzardConnector $blizzard) {}

    /**
     * @throws RequestException when Battle.net rejects the code.
     * @throws FatalRequestException when Battle.net is unreachable.
     */
    public function handle(#[SensitiveParameter] string $code): AccessTokenAuthenticator
    {
        $oauthConfig = $this->blizzard->oauthConfig();

        return $this->blizzard->send(new GetAuthorizationCodeTokenRequest(
            code: $code,
            clientId: $oauthConfig->getClientId(),
            clientSecret: $oauthConfig->getClientSecret(),
            tokenUrl: $oauthConfig->getTokenEndpoint(),
            redirectUri: $oauthConfig->getRedirectUri(),
        ))->dto();
    }
}
