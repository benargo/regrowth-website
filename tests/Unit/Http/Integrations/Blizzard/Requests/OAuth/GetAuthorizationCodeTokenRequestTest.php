<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\OAuth;

use App\Http\Integrations\Blizzard\Requests\OAuth\GetAuthorizationCodeTokenRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

#[Group('blizzard-integration')]
class GetAuthorizationCodeTokenRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_leaves_the_expiry_empty_when_battle_net_omits_expires_in(): void
    {
        Saloon::fake([
            GetAuthorizationCodeTokenRequest::class => MockResponse::make([
                'access_token' => 'user-token',
                'token_type' => 'bearer',
            ]),
        ]);

        $authenticator = $this->makeConnector()->send(new GetAuthorizationCodeTokenRequest(
            code: 'the-code',
            clientId: 'test_id',
            clientSecret: 'test_secret',
            tokenUrl: 'https://eu.battle.net/oauth/token',
            redirectUri: 'https://regrowth.test/auth/battlenet/callback',
        ))->dto();

        $this->assertSame('user-token', $authenticator->getAccessToken());
        $this->assertNull($authenticator->getExpiresAt());
    }
}
