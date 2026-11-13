<?php

namespace Tests\Feature\Auth;

use App\Models\DesktopClient;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('characters')]
#[Group('auth')]
class DesktopTokenBindingTest extends TestCase
{
    use RefreshDatabase;

    private const string VERIFIER = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_';

    private const string REDIRECT_URI = 'http://127.0.0.1:53123/callback';

    private const string DEVICE_ID = '9d4c2f3e-6a1b-4c8e-9f0a-1b2c3d4e5f60';

    private const string OTHER_DEVICE_ID = '1f2e3d4c-5b6a-4978-8a6b-5c4d3e2f1a0b';

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->client = app(ClientRepository::class)->createAuthorizationCodeGrantClient(
            'Regrowth Desktop',
            ['http://127.0.0.1/callback'],
            confidential: false,
        );
    }

    #[Test]
    #[Group('happy-path')]
    public function it_binds_the_issued_token_to_the_device_header(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client']);

        $this->signIn($user, self::DEVICE_ID)->assertOk();

        $token = Token::where('user_id', $user->id)->sole();
        $desktopClient = DesktopClient::findOrFail(self::DEVICE_ID);

        $this->assertSame($user->id, $desktopClient->user_id);
        $this->assertSame('Raid PC', $desktopClient->name);
        $this->assertSame($token->id, $desktopClient->oauth_access_token_id);
        $this->assertDatabaseHas('desktop_client_tokens', ['access_token_id' => $token->id, 'desktop_client_id' => self::DEVICE_ID]);
    }

    #[Test]
    #[Group('validation')]
    public function it_requires_the_device_headers_on_the_token_request(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client']);

        foreach ([[], ['X-Desktop-Client-Id' => 'not-a-uuid', 'X-Desktop-Client-Name' => 'Raid PC'], ['X-Desktop-Client-Id' => self::DEVICE_ID, 'X-Desktop-Client-Name' => ' ']] as $headers) {
            $this->post('/oauth/token', $this->tokenRequest($this->authorizationCodeFor($user)), $headers)
                ->assertBadRequest()
                ->assertJson(['error' => 'invalid_request']);
        }

        $this->assertSame(0, Token::count());
        $this->assertSame(0, DesktopClient::count());
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_a_refresh_from_another_device(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client']);
        DesktopClient::factory()->create(['id' => self::OTHER_DEVICE_ID, 'user_id' => $user->id]);
        $refreshToken = $this->signIn($user, self::DEVICE_ID)->json('refresh_token');

        $this->refresh($refreshToken, self::OTHER_DEVICE_ID)
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'invalid_grant']);

        $token = Token::where('user_id', $user->id)->sole();

        $this->assertFalse($token->revoked);
        $this->assertFalse($token->refreshToken->revoked);
    }

    #[Test]
    #[Group('happy-path')]
    public function it_binds_the_refreshed_token_to_the_same_device(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client']);
        $refreshToken = $this->signIn($user, self::DEVICE_ID)->json('refresh_token');
        $firstToken = Token::where('user_id', $user->id)->sole();

        $this->refresh($refreshToken, self::DEVICE_ID)->assertOk();

        $refreshedToken = Token::where('user_id', $user->id)->whereKeyNot($firstToken->id)->sole();

        $this->assertSame($refreshedToken->id, DesktopClient::findOrFail(self::DEVICE_ID)->oauth_access_token_id);
        $this->assertDatabaseHas('desktop_client_tokens', ['access_token_id' => $firstToken->id, 'desktop_client_id' => self::DEVICE_ID]);
        $this->assertDatabaseHas('desktop_client_tokens', ['access_token_id' => $refreshedToken->id, 'desktop_client_id' => self::DEVICE_ID]);
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_an_eleventh_desktop_client_with_a_clear_message(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client']);
        DesktopClient::factory()->linked()->count(10)->create(['user_id' => $user->id]);

        $this->signIn($user, self::DEVICE_ID)
            ->assertForbidden()
            ->assertExactJson([
                'error' => 'too_many_desktop_clients',
                'message' => 'You have 10 desktop apps signed in. Sign out of one on your regrowth.gg account page, then try again.',
            ]);

        $issuedToken = Token::where('user_id', $user->id)->where('client_id', $this->client->id)->sole();

        $this->assertTrue($issuedToken->revoked);
        $this->assertTrue($issuedToken->refreshToken->revoked);
        $this->assertNull(DesktopClient::find(self::DEVICE_ID));
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_a_sign_in_from_a_device_that_belongs_to_another_user(): void
    {
        $owner = $this->userWithPermissions(['use-desktop-client']);
        DesktopClient::factory()->create(['id' => self::DEVICE_ID, 'user_id' => $owner->id]);
        $user = $this->userWithPermissions(['use-desktop-client']);

        $this->signIn($user, self::DEVICE_ID)
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'invalid_grant']);

        $issuedToken = Token::where('user_id', $user->id)->where('client_id', $this->client->id)->sole();

        $this->assertTrue($issuedToken->revoked);
        $this->assertTrue($issuedToken->refreshToken->revoked);
        $this->assertSame($owner->id, DesktopClient::findOrFail(self::DEVICE_ID)->user_id);
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_a_refresh_with_an_undecryptable_refresh_token(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client']);
        $this->signIn($user, self::DEVICE_ID)->assertOk();

        $this->refresh('not-a-valid-refresh-token', self::DEVICE_ID)
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'invalid_grant']);

        $this->assertSame(1, Token::where('user_id', $user->id)->where('client_id', $this->client->id)->count());
    }

    // ==================== helpers ====================

    private function signIn(User $user, string $deviceId): TestResponse
    {
        return $this->post('/oauth/token', $this->tokenRequest($this->authorizationCodeFor($user)), $this->deviceHeaders($deviceId));
    }

    private function refresh(string $refreshToken, string $deviceId): TestResponse
    {
        return $this->post('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->client->id,
            'refresh_token' => $refreshToken,
        ], $this->deviceHeaders($deviceId));
    }

    /**
     * @return array<string, string>
     */
    private function deviceHeaders(string $deviceId): array
    {
        return [
            'X-Desktop-Client-Id' => $deviceId,
            'X-Desktop-Client-Name' => 'Raid PC',
        ];
    }

    /**
     * Render the consent page, approve it and return the authorization code from the loopback redirect.
     */
    private function authorizationCodeFor(User $user): string
    {
        $authorizeUrl = route('passport.authorizations.authorize').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->client->id,
            'redirect_uri' => self::REDIRECT_URI,
            'scope' => 'grm:upload grm:targets',
            'state' => 'abc123',
            'code_challenge' => strtr(rtrim(base64_encode(hash('sha256', self::VERIFIER, true)), '='), '+/', '-_'),
            'code_challenge_method' => 'S256',
        ]);

        $authToken = $this->actingAs($user)->get($authorizeUrl)->inertiaProps('authToken');

        $location = (string) $this->actingAs($user)
            ->post(route('passport.authorizations.approve'), ['auth_token' => $authToken])
            ->assertRedirect()
            ->headers->get('Location');

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        $this->assertNotEmpty($query['code'] ?? null);

        return $query['code'];
    }

    /**
     * @return array<string, string>
     */
    private function tokenRequest(string $code): array
    {
        return [
            'grant_type' => 'authorization_code',
            'client_id' => $this->client->id,
            'redirect_uri' => self::REDIRECT_URI,
            'code_verifier' => self::VERIFIER,
            'code' => $code,
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
        }

        $user = User::factory()->create();
        $user->discordRoles()->attach($role->id);
        $user->load('discordRoles.permissions');

        return $user;
    }
}
