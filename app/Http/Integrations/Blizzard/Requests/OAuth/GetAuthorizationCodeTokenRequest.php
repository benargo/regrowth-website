<?php

namespace App\Http\Integrations\Blizzard\Requests\OAuth;

use Saloon\Contracts\Authenticator;
use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Auth\AccessTokenAuthenticator;
use Saloon\Http\Auth\BasicAuthenticator;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Traits\Body\HasFormBody;
use SensitiveParameter;

/**
 * Exchange an authorization code for a user access token.
 */
class GetAuthorizationCodeTokenRequest extends Request implements HasBody
{
    use HasFormBody;

    protected Method $method = Method::POST;

    public ?bool $allowBaseUrlOverride = true;

    public function __construct(
        #[SensitiveParameter]
        protected string $code,
        protected string $clientId,
        #[SensitiveParameter]
        protected string $clientSecret,
        protected string $tokenUrl,
        protected string $redirectUri,
    ) {}

    public function resolveEndpoint(): string
    {
        return $this->tokenUrl;
    }

    protected function defaultAuth(): Authenticator
    {
        return new BasicAuthenticator($this->clientId, $this->clientSecret);
    }

    /**
     * @return array{grant_type: string, code: string, redirect_uri: string}
     */
    protected function defaultBody(): array
    {
        return [
            'grant_type' => 'authorization_code',
            'code' => $this->code,
            'redirect_uri' => $this->redirectUri,
        ];
    }

    public function createDtoFromResponse(Response $response): AccessTokenAuthenticator
    {
        $expiresIn = $response->json('expires_in');

        return new AccessTokenAuthenticator(
            accessToken: $response->json('access_token'),
            expiresAt: is_numeric($expiresIn) ? now()->addSeconds((int) $expiresIn)->toDateTimeImmutable() : null,
        );
    }
}
