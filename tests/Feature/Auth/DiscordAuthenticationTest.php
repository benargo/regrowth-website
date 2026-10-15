<?php

namespace Tests\Feature\Auth;

use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Exceptions\RateLimitedException;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildMemberRequest;
use App\Models\DiscordRole;
use App\Models\User;
use Exception;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\DashboardTestCase;
use Tests\Support\Discord\MocksDiscordService;

#[Group('auth')]
#[Group('discord-integration')]
class DiscordAuthenticationTest extends DashboardTestCase
{
    use MocksDiscordService;

    // ==================== redirect ====================

    #[Test]
    public function login_redirects_to_discord(): void
    {
        $response = $this->get('/login');

        $response->assertRedirect();
        $this->assertStringContainsString('discord.com', $response->headers->get('Location'));
    }

    #[Test]
    public function login_requests_the_identify_and_guild_member_scopes(): void
    {
        $response = $this->get('/login');

        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $scopes = explode(' ', $query['scope']);
        $this->assertContains('identify', $scopes);
        $this->assertContains('guilds.members.read', $scopes);
    }

    // ==================== callback ====================

    #[Test]
    public function discord_callback_creates_new_user(): void
    {
        DiscordRole::find('829022020301094922') ??
            DiscordRole::factory()->member()->create();

        $this->mockDiscordOAuth();
        $this->fakeDiscordGuildMember();

        $response = $this->get('/auth/discord/callback?code=test_code');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
            'nickname' => 'TestNickname',
            'avatar' => 'user_avatar_hash',
            'guild_avatar' => 'guild_avatar_hash',
        ]);

        $user = User::find('123456789012345678');
        $this->assertTrue($user->discordRoles->contains('name', 'Member'));

        $response->assertRedirect('/');
    }

    #[Test]
    public function discord_callback_remembers_the_login(): void
    {
        $this->mockDiscordOAuth();
        $this->fakeDiscordGuildMember();

        $response = $this->get('/auth/discord/callback?code=test_code');

        $guard = Auth::guard();
        $this->assertInstanceOf(SessionGuard::class, $guard);
        $response->assertCookie($guard->getRecallerName());
    }

    #[Test]
    public function discord_callback_redirects_to_the_intended_url(): void
    {
        $this->mockDiscordOAuth();
        $this->fakeDiscordGuildMember();

        $response = $this->withSession(['url.intended' => route('management.dashboard')])
            ->get('/auth/discord/callback?code=test_code');

        $response->assertRedirect(route('management.dashboard'));
    }

    #[Test]
    public function discord_callback_resets_the_discriminator_when_the_oauth_payload_omits_it(): void
    {
        User::factory()->create([
            'id' => '123456789012345678',
            'discriminator' => '1234',
        ]);

        $this->mockDiscordOAuth(omit: ['discriminator']);
        $this->fakeDiscordGuildMember();

        $this->get('/auth/discord/callback?code=test_code');

        $this->assertDatabaseHas('users', [
            'id' => '123456789012345678',
            'discriminator' => '0',
        ]);
    }

    #[Test]
    public function discord_callback_updates_existing_user(): void
    {
        User::factory()->create([
            'id' => '123456789012345678',
            'username' => 'oldusername',
            'discriminator' => '1234',
            'nickname' => 'OldNick',
        ]);

        $this->mockDiscordOAuth();
        $this->fakeDiscordGuildMember(['nick' => 'NewNickname']);

        $response = $this->get('/auth/discord/callback?code=test_code');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'id' => '123456789012345678',
            'username' => 'testuser', // Updated from OAuth
            'nickname' => 'NewNickname',
        ]);
        $response->assertRedirect('/');
    }

    #[Test]
    public function discord_callback_stores_null_profile_fields_when_the_member_payload_omits_them(): void
    {
        $this->mockDiscordOAuth();

        Saloon::fake([GetGuildMemberRequest::class => MockResponse::make(
            Arr::except($this->makeDiscordGuildMemberResponse(), ['nick', 'avatar', 'banner']),
        )]);

        $this->get('/auth/discord/callback?code=test_code');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'id' => '123456789012345678',
            'nickname' => null,
            'guild_avatar' => null,
        ]);
    }

    #[Test]
    public function discord_callback_keeps_existing_profile_fields_when_the_member_payload_omits_them(): void
    {
        User::factory()->create([
            'id' => '123456789012345678',
            'nickname' => 'OldNick',
            'guild_avatar' => 'old_guild_avatar',
        ]);

        $this->mockDiscordOAuth();

        Saloon::fake([GetGuildMemberRequest::class => MockResponse::make(
            Arr::except($this->makeDiscordGuildMemberResponse(), ['nick', 'avatar', 'banner']),
        )]);

        $this->get('/auth/discord/callback?code=test_code');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'id' => '123456789012345678',
            'nickname' => 'OldNick',
            'guild_avatar' => 'old_guild_avatar',
        ]);
    }

    #[Test]
    public function discord_callback_fails_when_user_not_in_guild(): void
    {
        $this->mockDiscordOAuth();

        Saloon::fake([GetGuildMemberRequest::class => $this->makeDiscordErrorResponse(404, 10007, 'Unknown Member')]);

        $response = $this->get('/auth/discord/callback?code=test_code');

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'You must be a member of the Regrowth Discord server to log in.');
    }

    #[Test]
    public function discord_callback_fails_on_guild_api_error(): void
    {
        Exceptions::fake();
        $this->mockDiscordOAuth();

        Saloon::fake([GetGuildMemberRequest::class => $this->makeDiscordErrorResponse(403, 50001, 'Missing Access')]);

        $response = $this->get('/auth/discord/callback?code=test_code');

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Failed to verify your Discord server membership. Please try again.');
        Exceptions::assertReported(fn (ApiException $exception): bool => $exception->getDiscordCode() === 50001);
    }

    #[Test]
    public function discord_callback_fails_when_discord_rate_limits_the_member_lookup(): void
    {
        Exceptions::fake();
        $this->mockDiscordOAuth();

        Saloon::fake([GetGuildMemberRequest::class => $this->makeDiscordRateLimitResponse(5.0)]);

        $response = $this->get('/auth/discord/callback?code=test_code');

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Failed to verify your Discord server membership. Please try again.');
        Exceptions::assertReported(RateLimitedException::class);
    }

    #[Test]
    public function discord_callback_fails_when_the_member_lookup_cannot_connect(): void
    {
        Exceptions::fake();
        $this->mockDiscordOAuth();

        Saloon::fake([
            GetGuildMemberRequest::class => MockResponse::make()->throw(
                fn (PendingRequest $pendingRequest): FatalRequestException => new FatalRequestException(new RuntimeException('Connection refused'), $pendingRequest),
            ),
        ]);

        $response = $this->get('/auth/discord/callback?code=test_code');

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Failed to verify your Discord server membership. Please try again.');
        Exceptions::assertReported(FatalRequestException::class);
    }

    #[Test]
    public function discord_callback_handles_oauth_failure(): void
    {
        $this->mockSocialiteProvider()
            ->shouldReceive('user')
            ->andThrow(new Exception('OAuth failed'));

        $response = $this->get('/auth/discord/callback?code=test_code');

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Failed to authenticate with Discord. Please try again.');
    }

    // ==================== logout ====================

    #[Test]
    public function users_can_logout(): void
    {
        $user = User::factory()->createOne();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    #[Test]
    public function logout_invalidates_the_session(): void
    {
        $user = User::factory()->createOne();

        $response = $this->actingAs($user)
            ->withSession(['previous_value' => 'stale'])
            ->post('/logout');

        $response->assertSessionMissing('previous_value');
    }

    // ==================== access ====================

    #[Test]
    public function authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->officer()->createOne([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
        ]);

        $response = $this->actingAs($user)->get(route('management.dashboard'));

        $response->assertStatus(200);
    }

    #[Test]
    public function guest_is_redirected_to_login_when_accessing_dashboard(): void
    {
        $response = $this->get(route('management.dashboard'));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function authenticated_user_is_redirected_away_from_login(): void
    {
        $user = User::factory()->createOne();

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect('/');
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $rawOverrides
     * @param  list<string>  $omit
     */
    private function mockDiscordOAuth(array $rawOverrides = [], array $omit = []): void
    {
        $socialiteUser = (new SocialiteUser)
            ->setRaw(Arr::except([
                'id' => '123456789012345678',
                'username' => 'testuser',
                'discriminator' => '0',
                'avatar' => 'user_avatar_hash',
                'banner' => null,
                ...$rawOverrides,
            ], $omit))
            ->map(['id' => '123456789012345678', 'nickname' => 'testuser']);

        $this->mockSocialiteProvider()
            ->shouldReceive('user')
            ->andReturn($socialiteUser);
    }

    /**
     * Bind a Socialite factory whose `discord` driver is the returned provider mock.
     */
    private function mockSocialiteProvider(): MockInterface
    {
        $provider = $this->mock(Provider::class);

        $this->mock(Factory::class)
            ->shouldReceive('driver')
            ->with('discord')
            ->andReturn($provider);

        return $provider;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeDiscordGuildMember(array $overrides = []): void
    {
        Saloon::fake([GetGuildMemberRequest::class => MockResponse::make($this->makeDiscordGuildMemberResponse([
            'user' => [
                ...$this->makeDiscordGuildMemberResponse()['user'],
                'avatar' => 'user_avatar_hash',
            ],
            'nick' => 'TestNickname',
            'avatar' => 'guild_avatar_hash',
            'roles' => ['829022020301094922'],
            ...$overrides,
        ]))]);
    }
}
