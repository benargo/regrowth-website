<?php

namespace Tests\Feature\Auth;

use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Token;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('auth')]
class DesktopAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const string VERIFIER = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_';

    private const string WRONG_VERIFIER = '_-ZYXWVUTSRQPONMLKJIHGFEDCBAzyxwvutsrqponmlkjihgfedcba9876543210';

    private const string REDIRECT_URI = 'http://127.0.0.1:53123/callback';

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
    #[Group('authorization')]
    public function it_redirects_guests_from_the_consent_page_to_login(): void
    {
        $this->get($this->authorizeUrl())->assertRedirect(route('login'));
    }

    #[Test]
    #[Group('authorization')]
    public function it_forbids_the_consent_page_without_use_desktop_client(): void
    {
        $this->actingAs($this->userWithPermissions(['upload-grm-rosters']))
            ->get($this->authorizeUrl())
            ->assertForbidden();
    }

    #[Test]
    public function it_renders_the_consent_page(): void
    {
        $this->actingAs($this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters']))
            ->get($this->authorizeUrl())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Desktop/Authorize')
                ->where('clientName', 'Regrowth Desktop')
                ->has('scopes', 2)
                ->where('scopes.0.id', 'grm:upload')
                ->where('scopes.1.id', 'grm:targets')
                ->whereType('authToken', 'string')
                ->whereType('csrfToken', 'string'));
    }

    #[Test]
    public function it_shares_the_viewers_ban_permissions_with_the_consent_page(): void
    {
        $this->actingAs($this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'create-grm-bans']))
            ->get($this->authorizeUrl())
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Desktop/Authorize')
                ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('create-grm-bans')));
    }

    #[Test]
    public function it_accepts_a_loopback_redirect_on_any_port(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client']);

        foreach (['http://127.0.0.1:53123/callback', 'http://127.0.0.1:61000/callback'] as $redirectUri) {
            $this->actingAs($user)
                ->get($this->authorizeUrl(['redirect_uri' => $redirectUri]))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('Auth/Desktop/Authorize'));
        }
    }

    #[Test]
    #[Group('authorization')]
    public function it_refuses_a_redirect_that_is_not_the_loopback_callback(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client']);

        foreach (['http://127.0.0.1:53123/other', 'http://localhost:53123/callback', 'https://evil.example/callback'] as $redirectUri) {
            $this->actingAs($user)
                ->get($this->authorizeUrl(['redirect_uri' => $redirectUri]))
                ->assertUnauthorized()
                ->assertJson(['error' => 'invalid_client'])
                ->assertSessionMissing('authRequest');
        }
    }

    #[Test]
    public function it_requires_pkce_from_the_public_client(): void
    {
        $this->actingAs($this->userWithPermissions(['use-desktop-client']))
            ->get($this->authorizeUrl(['code_challenge' => null, 'code_challenge_method' => null]))
            ->assertBadRequest()
            ->assertJson(['error' => 'invalid_request'])
            ->assertSessionMissing('authRequest');
    }

    // ==================== approve and token ====================

    #[Test]
    public function it_issues_scoped_tokens_for_an_approved_request_with_the_verifier(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client']);

        $this->post('/oauth/token', $this->tokenRequest($this->authorizationCodeFor($user)), $this->deviceHeaders())
            ->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token']);

        $this->assertSame(['grm:upload', 'grm:targets'], Token::where('user_id', $user->id)->sole()->scopes);
    }

    #[Test]
    public function it_refuses_the_token_request_with_the_wrong_verifier(): void
    {
        $code = $this->authorizationCodeFor($this->userWithPermissions(['use-desktop-client']));

        $this->post('/oauth/token', $this->tokenRequest($code, ['code_verifier' => self::WRONG_VERIFIER]), $this->deviceHeaders())
            ->assertBadRequest()
            ->assertJson(['error' => 'invalid_grant']);
    }

    #[Test]
    public function it_refuses_the_token_request_with_another_port(): void
    {
        $code = $this->authorizationCodeFor($this->userWithPermissions(['use-desktop-client']));

        $this->post('/oauth/token', $this->tokenRequest($code, ['redirect_uri' => 'http://127.0.0.1:61000/callback']), $this->deviceHeaders())
            ->assertBadRequest()
            ->assertJson(['error' => 'invalid_request']);
    }

    // ==================== deny ====================

    #[Test]
    public function it_redirects_a_denied_request_to_the_loopback_with_access_denied(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client']);
        $authToken = $this->actingAs($user)->get($this->authorizeUrl())->inertiaProps('authToken');

        $response = $this->actingAs($user)
            ->delete(route('passport.authorizations.deny'), ['auth_token' => $authToken])
            ->assertRedirect();

        $query = $this->callbackQuery($response);

        $this->assertSame('access_denied', $query['error'] ?? null);
        $this->assertSame('abc123', $query['state'] ?? null);
    }

    // ==================== device routes ====================

    #[Test]
    #[Group('deprecated')]
    public function it_no_longer_serves_the_device_routes(): void
    {
        $this->assertContains($this->post('/oauth/device/code')->getStatusCode(), [404, 405]);
        $this->assertContains($this->get('/oauth/device')->getStatusCode(), [404, 405]);
        $this->assertContains($this->get('/link')->getStatusCode(), [404, 405]);
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, string|null>  $overrides
     */
    private function authorizeUrl(array $overrides = []): string
    {
        return route('passport.authorizations.authorize').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->client->id,
            'redirect_uri' => self::REDIRECT_URI,
            'scope' => 'grm:upload grm:targets',
            'state' => 'abc123',
            'code_challenge' => strtr(rtrim(base64_encode(hash('sha256', self::VERIFIER, true)), '='), '+/', '-_'),
            'code_challenge_method' => 'S256',
            ...$overrides,
        ]);
    }

    /**
     * Render the consent page, approve it and return the authorization code from the loopback redirect.
     */
    private function authorizationCodeFor(User $user): string
    {
        $authToken = $this->actingAs($user)->get($this->authorizeUrl())->inertiaProps('authToken');

        $response = $this->actingAs($user)
            ->post(route('passport.authorizations.approve'), ['auth_token' => $authToken])
            ->assertRedirect();

        $query = $this->callbackQuery($response);

        $this->assertSame('abc123', $query['state'] ?? null);
        $this->assertNotEmpty($query['code'] ?? null);

        return $query['code'];
    }

    /**
     * Assert the response redirects to the loopback callback and return its query parameters.
     *
     * @return array<string, string>
     */
    private function callbackQuery(TestResponse $response): array
    {
        $location = (string) $response->headers->get('Location');

        $this->assertSame(self::REDIRECT_URI, strtok($location, '?'));

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    /**
     * @return array<string, string>
     */
    private function deviceHeaders(): array
    {
        return [
            'X-Desktop-Client-Id' => '9d4c2f3e-6a1b-4c8e-9f0a-1b2c3d4e5f60',
            'X-Desktop-Client-Name' => 'Raid PC',
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function tokenRequest(string $code, array $overrides = []): array
    {
        return [
            'grant_type' => 'authorization_code',
            'client_id' => $this->client->id,
            'redirect_uri' => self::REDIRECT_URI,
            'code_verifier' => self::VERIFIER,
            'code' => $code,
            ...$overrides,
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
