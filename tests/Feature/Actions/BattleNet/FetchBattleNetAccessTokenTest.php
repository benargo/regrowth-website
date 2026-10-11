<?php

namespace Tests\Feature\Actions\BattleNet;

use App\Actions\BattleNet\FetchBattleNetAccessToken;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\ApiException;
use App\Http\Integrations\Blizzard\Middleware\EagerlyMirrorAssets;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\Requests\OAuth\GetAuthorizationCodeTokenRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\OAuth2\GetClientCredentialsTokenBasicAuthRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('auth')]
#[Group('blizzard-integration')]
class FetchBattleNetAccessTokenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(BlizzardConnector::class, new BlizzardConnector(
            clientId: 'test_id',
            clientSecret: 'test_secret',
            region: Region::EU,
            locale: Region::EU->defaultLocale(),
            redirectUri: 'https://regrowth.test/auth/battlenet/callback',
            eagerlyMirrorAssets: $this->app->make(EagerlyMirrorAssets::class),
        ));
    }

    #[Test]
    #[Group('happy-path')]
    public function it_exchanges_the_code_for_a_user_access_token(): void
    {
        $this->fakeTokenExchange();
        $this->freezeTime();

        $authenticator = FetchBattleNetAccessToken::run('the-code');

        $this->assertSame('user-token', $authenticator->getAccessToken());
        $this->assertSame(now()->addSeconds(86399)->getTimestamp(), $authenticator->getExpiresAt()?->getTimestamp());
        $this->assertFalse($authenticator->isRefreshable());

        Saloon::assertNotSent(GetClientCredentialsTokenBasicAuthRequest::class);
        Saloon::assertSent(function (GetAuthorizationCodeTokenRequest $request, $response): bool {
            $pending = $response->getPendingRequest();

            return $pending->getUrl() === 'https://eu.battle.net/oauth/token'
                && $pending->headers()->get('Authorization') === 'Basic '.base64_encode('test_id:test_secret')
                && $pending->body()->all() === [
                    'grant_type' => 'authorization_code',
                    'code' => 'the-code',
                    'redirect_uri' => 'https://regrowth.test/auth/battlenet/callback',
                ];
        });
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_when_battle_net_rejects_the_code(): void
    {
        Saloon::fake([
            GetAuthorizationCodeTokenRequest::class => MockResponse::make(['error' => 'invalid_grant'], 400),
        ]);

        $this->expectException(ApiException::class);

        FetchBattleNetAccessToken::run('stale-code');
    }

    // ==================== helpers ====================

    private function fakeTokenExchange(): void
    {
        Saloon::fake([
            GetAuthorizationCodeTokenRequest::class => MockResponse::make([
                'access_token' => 'user-token',
                'token_type' => 'bearer',
                'expires_in' => 86399,
                'scope' => 'openid wow.profile',
            ]),
        ]);
    }
}
