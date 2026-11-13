<?php

namespace Tests\Feature\Actions\DesktopClient;

use App\Actions\DesktopClient\BindDesktopClientToken;
use App\Exceptions\TooManyDesktopClientsException;
use App\Models\DesktopClient;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
#[Group('auth')]
class BindDesktopClientTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('happy-path')]
    public function it_binds_a_new_sign_in_to_a_new_client(): void
    {
        $user = User::factory()->create();
        $deviceId = fake()->uuid();
        $token = $this->accessTokenFor($user);

        $desktopClient = BindDesktopClientToken::run($user, $deviceId, 'Raid PC', $token->id, null);

        $this->assertSame($deviceId, $desktopClient->id);
        $this->assertSame($user->id, $desktopClient->user_id);
        $this->assertSame('Raid PC', $desktopClient->name);
        $this->assertSame($token->id, $desktopClient->oauth_access_token_id);
        $this->assertDatabaseHas('desktop_client_tokens', ['access_token_id' => $token->id, 'desktop_client_id' => $deviceId]);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_binds_a_refreshed_token_to_the_same_client(): void
    {
        $user = User::factory()->create();
        $deviceId = fake()->uuid();
        $firstToken = $this->accessTokenFor($user);
        $refreshedToken = $this->accessTokenFor($user);
        BindDesktopClientToken::run($user, $deviceId, 'Raid PC', $firstToken->id, null);

        $desktopClient = BindDesktopClientToken::run($user, $deviceId, 'Raid PC', $refreshedToken->id, $firstToken->id);

        $this->assertSame(1, DesktopClient::count());
        $this->assertSame($refreshedToken->id, $desktopClient->oauth_access_token_id);
        $this->assertEqualsCanonicalizing(
            [$firstToken->id, $refreshedToken->id],
            $desktopClient->tokens()->pluck('access_token_id')->all(),
        );
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_a_refresh_from_another_device(): void
    {
        $user = User::factory()->create();
        $deviceId = fake()->uuid();
        $otherDeviceId = DesktopClient::factory()->create(['user_id' => $user->id])->id;
        $firstToken = $this->accessTokenFor($user);
        $refreshedToken = $this->accessTokenFor($user);
        BindDesktopClientToken::run($user, $deviceId, 'Raid PC', $firstToken->id, null);

        try {
            BindDesktopClientToken::run($user, $otherDeviceId, 'Laptop', $refreshedToken->id, $firstToken->id);
            $this->fail('Expected an AuthenticationException.');
        } catch (AuthenticationException) {
            $this->assertDatabaseMissing('desktop_client_tokens', ['access_token_id' => $refreshedToken->id]);
        }
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_a_device_id_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        $foreignClient = DesktopClient::factory()->create(['name' => 'Their PC']);
        $token = $this->accessTokenFor($user);

        $foreignMessage = $this->authenticationFailureMessage(
            fn () => BindDesktopClientToken::run($user, $foreignClient->id, 'Hijack', $token->id, null),
        );
        $mismatchMessage = $this->authenticationFailureMessage(
            fn () => BindDesktopClientToken::run($user, fake()->uuid(), 'Raid PC', $token->id, $this->accessTokenFor($user)->id),
        );

        $this->assertSame($mismatchMessage, $foreignMessage);
        $this->assertSame('Their PC', $foreignClient->fresh()->name);
        $this->assertSame($foreignClient->user_id, $foreignClient->fresh()->user_id);
        $this->assertDatabaseMissing('desktop_client_tokens', ['access_token_id' => $token->id]);
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_an_eleventh_client(): void
    {
        $user = User::factory()->create();
        $linkedClients = DesktopClient::factory()->linked()->count(10)->create(['user_id' => $user->id]);

        try {
            BindDesktopClientToken::run($user, fake()->uuid(), 'Eleventh PC', $this->accessTokenFor($user)->id, null);
            $this->fail('Expected a TooManyDesktopClientsException.');
        } catch (TooManyDesktopClientsException $exception) {
            $this->assertSame(
                'You have 10 desktop apps signed in. Sign out of one on your regrowth.gg account page, then try again.',
                $exception->getMessage(),
            );
        }

        $this->assertSame(10, DesktopClient::count());

        $token = $this->accessTokenFor($user);
        $desktopClient = BindDesktopClientToken::run($user, $linkedClients->first()->id, 'Raid PC', $token->id, null);

        $this->assertSame($token->id, $desktopClient->oauth_access_token_id);
    }

    #[Test]
    public function it_does_not_count_signed_out_clients(): void
    {
        $user = User::factory()->create();
        $linkedClients = DesktopClient::factory()->linked()->count(10)->create(['user_id' => $user->id]);
        $linkedClients->first()->accessToken->revoke();
        $deviceId = fake()->uuid();

        $desktopClient = BindDesktopClientToken::run($user, $deviceId, 'New PC', $this->accessTokenFor($user)->id, null);

        $this->assertSame($deviceId, $desktopClient->id);
    }

    // ==================== helpers ====================

    private function accessTokenFor(User $user): Token
    {
        $token = Token::forceCreate([
            'id' => Str::random(80),
            'user_id' => $user->id,
            'client_id' => Client::factory()->create()->getKey(),
            'name' => null,
            'scopes' => ['grm:upload', 'grm:targets'],
            'revoked' => false,
            'expires_at' => now()->addHour(),
        ]);

        RefreshToken::forceCreate([
            'id' => Str::random(80),
            'access_token_id' => $token->id,
            'revoked' => false,
            'expires_at' => now()->addDays(90),
        ]);

        return $token;
    }

    private function authenticationFailureMessage(callable $bind): string
    {
        try {
            $bind();
        } catch (AuthenticationException $exception) {
            return $exception->getMessage();
        }

        $this->fail('Expected an AuthenticationException.');
    }
}
