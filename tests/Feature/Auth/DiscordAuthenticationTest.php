<?php

namespace Tests\Feature\Auth;

use App\Models\DiscordRole;
use App\Models\User;
use App\Services\Discord\DiscordGuildService;
use App\Services\Discord\Exceptions\UserNotInGuildException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscordAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function login_redirects_to_discord(): void
    {
        $response = $this->get('/login');

        $response->assertRedirect();
        $this->assertStringContainsString('discord.com', $response->headers->get('Location'));
    }

    #[Test]
    public function discord_callback_creates_new_user(): void
    {
        DiscordRole::find('829022020301094922') ??
            DiscordRole::factory()->member()->create();

        $this->mockDiscordOAuth();
        $this->mockDiscordGuildService();

        $response = $this->get('/auth/discord/callback?code=test_code');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'id' => '123456789012345678',
            'username' => 'testuser',
        ]);

        $user = User::find('123456789012345678');
        $this->assertTrue($user->discordRoles->contains('name', 'Member'));

        $response->assertRedirect('/');
    }

    #[Test]
    public function discord_callback_updates_existing_user(): void
    {
        $guest = DiscordRole::find('829022292590985226') ??
            DiscordRole::factory()->guest()->create();
        $officer = DiscordRole::find('829021769448816691') ??
            DiscordRole::factory()->officer()->create();

        $user = User::factory()->create([
            'id' => '123456789012345678',
            'username' => 'oldusername',
            'discriminator' => '1234',
            'nickname' => 'OldNick',
        ]);
        $user->discordRoles()->attach($guest->id);

        $this->mockDiscordOAuth();
        $this->mockDiscordGuildService([
            'nick' => 'NewNickname',
            'roles' => [$officer->id], // New Officer role from Discord API
        ]);

        $response = $this->get('/auth/discord/callback?code=test_code');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'id' => '123456789012345678',
            'username' => 'testuser', // Updated from OAuth
            'nickname' => 'NewNickname',
        ]);

        $updatedUser = User::find('123456789012345678');
        $this->assertTrue($updatedUser->discordRoles->contains('name', 'Officer'));
        $this->assertFalse($updatedUser->discordRoles->contains('name', 'Guest'));
        $response->assertRedirect('/');
    }

    #[Test]
    public function discord_callback_fails_when_user_not_in_guild(): void
    {
        $this->mockDiscordOAuth();

        $this->mock(DiscordGuildService::class, function ($mock) {
            $mock->shouldReceive('getGuildMember')
                ->andThrow(new UserNotInGuildException('User is not a member of the guild'));
        });

        $response = $this->get('/auth/discord/callback?code=test_code');

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'You must be a member of the Regrowth Discord server to log in.');
    }

    #[Test]
    public function discord_callback_fails_on_guild_api_error(): void
    {
        $this->mockDiscordOAuth();

        $this->mock(DiscordGuildService::class, function ($mock) {
            $mock->shouldReceive('getGuildMember')
                ->andThrow(new \RuntimeException('API Error'));
        });

        $response = $this->get('/auth/discord/callback?code=test_code');

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Failed to verify your Discord server membership. Please try again.');
    }

    #[Test]
    public function discord_callback_handles_oauth_failure(): void
    {
        Socialite::shouldReceive('driver')
            ->with('discord')
            ->andReturnSelf();
        Socialite::shouldReceive('user')
            ->andThrow(new \Exception('OAuth failed'));

        $response = $this->get('/auth/discord/callback?code=test_code');

        $this->assertGuest();
        $response->assertRedirect('/');
        $response->assertSessionHas('error', 'Failed to authenticate with Discord. Please try again.');
    }

    #[Test]
    public function users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    #[Test]
    public function authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->officer()->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    #[Test]
    public function guest_is_redirected_to_login_when_accessing_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function authenticated_user_is_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/login');

        $response->assertRedirect('/');
    }

    private function mockDiscordOAuth(): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('123456789012345678');
        $socialiteUser->shouldReceive('getNickname')->andReturn('testuser');
        $socialiteUser->shouldReceive('getRaw')->andReturn([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
            'avatar' => 'user_avatar_hash',
            'banner' => null,
        ]);

        Socialite::shouldReceive('driver')
            ->with('discord')
            ->andReturnSelf();
        Socialite::shouldReceive('scopes')
            ->andReturnSelf();
        Socialite::shouldReceive('redirect')
            ->andReturn(redirect('https://discord.com/oauth2/authorize'));
        Socialite::shouldReceive('user')
            ->andReturn($socialiteUser);
    }

    private function mockDiscordGuildService(array $overrides = []): void
    {
        $defaultData = [
            'nick' => 'TestNickname',
            'avatar' => 'guild_avatar_hash',
            'banner' => null,
            'roles' => ['829022020301094922'],
        ];

        $this->mock(DiscordGuildService::class, function ($mock) use ($defaultData, $overrides) {
            $mock->shouldReceive('getGuildMember')
                ->andReturn(array_merge($defaultData, $overrides));
        });
    }
}
