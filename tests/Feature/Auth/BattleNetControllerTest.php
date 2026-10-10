<?php

namespace Tests\Feature\Auth;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Middleware\EagerlyMirrorAssets;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\Requests\OAuth\GetAuthorizationCodeTokenRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('auth')]
#[Group('blizzard-integration')]
class BattleNetControllerTest extends TestCase
{
    use RefreshDatabase;

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
    #[Group('authorization')]
    public function redirect_requires_authentication(): void
    {
        $this->get(route('battlenet.redirect'))->assertRedirect('/login');
    }

    #[Test]
    #[Group('happy-path')]
    public function redirect_sends_the_user_to_battle_net_and_remembers_the_state(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('battlenet.redirect'));

        $location = $response->headers->get('Location');
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('https://eu.battle.net/oauth/authorize?', $location);
        $this->assertSame('openid wow.profile', $query['scope']);
        $this->assertSame(32, strlen($query['state']));
        $response->assertSessionHas('battlenet.oauth_state', $query['state']);
    }

    // ==================== callback ====================

    #[Test]
    #[Group('authorization')]
    public function callback_requires_authentication(): void
    {
        $this->get(route('battlenet.callback', ['code' => 'c', 'state' => 's']))->assertRedirect('/login');
    }

    #[Test]
    #[Group('happy-path')]
    public function callback_stores_the_access_token_in_the_session(): void
    {
        $this->freezeTime();
        $this->fakeTokenExchange();

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['battlenet.oauth_state' => 'expected-state'])
            ->get(route('battlenet.callback', ['code' => 'the-code', 'state' => 'expected-state']));

        $response->assertRedirect(route('account.index'));
        $response->assertSessionHas('success', 'Connected to Battle.net.');
        $response->assertSessionMissing('battlenet.oauth_state');
        $response->assertSessionHas('battlenet.access_token', 'user-token');
        $response->assertSessionHas('battlenet.token_expires_at', now()->addSeconds(86399)->getTimestamp());
    }

    #[Test]
    #[Group('error-handling')]
    public function callback_rejects_a_missing_state(): void
    {
        $this->fakeTokenExchange();

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['battlenet.oauth_state' => 'expected-state'])
            ->get(route('battlenet.callback', ['code' => 'the-code']));

        $this->assertConnectFailed($response);
        Saloon::assertNotSent(GetAuthorizationCodeTokenRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function callback_rejects_a_mismatched_state(): void
    {
        $this->fakeTokenExchange();

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['battlenet.oauth_state' => 'expected-state'])
            ->get(route('battlenet.callback', ['code' => 'the-code', 'state' => 'forged-state']));

        $this->assertConnectFailed($response);
        Saloon::assertNotSent(GetAuthorizationCodeTokenRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function callback_rejects_a_request_with_no_state_in_the_session(): void
    {
        $this->fakeTokenExchange();

        $response = $this->actingAs(User::factory()->create())
            ->get(route('battlenet.callback', ['code' => 'the-code', 'state' => 'any-state']));

        $this->assertConnectFailed($response);
        Saloon::assertNotSent(GetAuthorizationCodeTokenRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function callback_rejects_a_missing_code(): void
    {
        $this->fakeTokenExchange();

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['battlenet.oauth_state' => 'expected-state'])
            ->get(route('battlenet.callback', ['state' => 'expected-state']));

        $this->assertConnectFailed($response);
        Saloon::assertNotSent(GetAuthorizationCodeTokenRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function callback_handles_the_user_denying_consent(): void
    {
        $this->fakeTokenExchange();

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['battlenet.oauth_state' => 'expected-state'])
            ->get(route('battlenet.callback', ['error' => 'access_denied', 'state' => 'expected-state']));

        $this->assertConnectFailed($response);
        Saloon::assertNotSent(GetAuthorizationCodeTokenRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function callback_handles_battle_net_rejecting_the_code(): void
    {
        Saloon::fake([
            GetAuthorizationCodeTokenRequest::class => MockResponse::make(['error' => 'invalid_grant'], 400),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['battlenet.oauth_state' => 'expected-state'])
            ->get(route('battlenet.callback', ['code' => 'stale-code', 'state' => 'expected-state']));

        $this->assertConnectFailed($response);
    }

    #[Test]
    #[Group('error-handling')]
    public function callback_logs_battle_net_rejecting_the_code_without_the_code(): void
    {
        Log::spy();

        Saloon::fake([
            GetAuthorizationCodeTokenRequest::class => MockResponse::make(['error' => 'invalid_grant'], 400),
        ]);

        $this->actingAs(User::factory()->create())
            ->withSession(['battlenet.oauth_state' => 'expected-state'])
            ->get(route('battlenet.callback', ['code' => 'stale-code', 'state' => 'expected-state']));

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Battle.net authorization code exchange failed.', ['status' => 400, 'error' => 'invalid_grant']);
    }

    #[Test]
    #[Group('error-handling')]
    public function callback_handles_battle_net_being_unreachable(): void
    {
        $this->fakeUnreachableTokenExchange();

        $response = $this->actingAs(User::factory()->create())
            ->withSession(['battlenet.oauth_state' => 'expected-state'])
            ->get(route('battlenet.callback', ['code' => 'the-code', 'state' => 'expected-state']));

        $this->assertConnectFailed($response);
    }

    #[Test]
    #[Group('error-handling')]
    public function callback_logs_battle_net_being_unreachable(): void
    {
        Log::spy();

        $this->fakeUnreachableTokenExchange();

        $this->actingAs(User::factory()->create())
            ->withSession(['battlenet.oauth_state' => 'expected-state'])
            ->get(route('battlenet.callback', ['code' => 'the-code', 'state' => 'expected-state']));

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Battle.net authorization code exchange failed.', ['message' => 'Connection refused']);
    }

    // ==================== helpers ====================

    private function fakeTokenExchange(): void
    {
        Saloon::fake([
            GetAuthorizationCodeTokenRequest::class => MockResponse::make([
                'access_token' => 'user-token',
                'token_type' => 'bearer',
                'expires_in' => 86399,
            ]),
        ]);
    }

    private function fakeUnreachableTokenExchange(): void
    {
        Saloon::fake([
            GetAuthorizationCodeTokenRequest::class => MockResponse::make()->throw(
                fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(new RuntimeException('Connection refused'), $pendingRequest),
            ),
        ]);
    }

    private function assertConnectFailed(TestResponse $response): void
    {
        $response->assertRedirect(route('account.index'));
        $response->assertSessionHas('error', 'Failed to connect to Battle.net. Please try again.');
        $response->assertSessionMissing('battlenet.access_token');
        $response->assertSessionMissing('battlenet.token_expires_at');
    }
}
