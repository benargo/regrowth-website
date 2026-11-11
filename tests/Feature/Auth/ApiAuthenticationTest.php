<?php

namespace Tests\Feature\Auth;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ApiTokenCookieFactory;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('auth')]
class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[Test]
    #[Group('authorization')]
    public function it_rejects_an_api_request_authenticated_only_by_the_web_session(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('api.discord.members.resolve', '999999999999999999'))
            ->assertUnauthorized();
    }

    #[Test]
    public function it_authenticates_an_api_request_with_the_laravel_token_cookie_from_a_web_page(): void
    {
        User::factory()->create(['id' => '999999999999999999']);

        $page = $this->actingAs($this->officerWhoCanResolveDiscordMembers())->get('/');
        $page->assertCookie(Passport::cookie());
        $apiToken = $page->getCookie(Passport::cookie(), false)->getValue();
        $csrfToken = session()->token();

        $this->app['auth']->forgetGuards();

        $this->withUnencryptedCookie(Passport::cookie(), $apiToken)
            ->withCredentials()
            ->withHeader('X-CSRF-TOKEN', $csrfToken)
            ->postJson(route('api.discord.members.resolve', '999999999999999999'))
            ->assertOk()
            ->assertExactJson(['status' => 'ready']);
    }

    #[Test]
    #[Group('authorization')]
    public function it_rejects_a_laravel_token_cookie_when_the_csrf_header_does_not_match(): void
    {
        $user = User::factory()->create();

        $this->withCookie(Passport::cookie(), $this->apiTokenCookie($user, 'cookie-csrf-token'))
            ->withCredentials()
            ->withHeader('X-CSRF-TOKEN', 'different-csrf-token')
            ->postJson(route('api.discord.members.resolve', '999999999999999999'))
            ->assertUnauthorized();
    }

    #[Test]
    #[Group('authorization')]
    public function it_rejects_an_expired_laravel_token_cookie(): void
    {
        $user = User::factory()->create();
        $this->travel(-config('session.lifetime') - 1)->minutes();
        $apiToken = $this->apiTokenCookie($user, 'csrf-token');
        $this->travelBack();

        $this->withCookie(Passport::cookie(), $apiToken)
            ->withCredentials()
            ->withHeader('X-CSRF-TOKEN', 'csrf-token')
            ->postJson(route('api.discord.members.resolve', '999999999999999999'))
            ->assertUnauthorized();
    }

    #[Test]
    public function it_authenticates_an_api_request_with_a_personal_access_bearer_token(): void
    {
        app(ClientRepository::class)->createPersonalAccessGrantClient('Test personal access client', 'users');
        User::factory()->create(['id' => '999999999999999999']);
        $officer = $this->officerWhoCanResolveDiscordMembers();

        $this->withToken($officer->createToken('test')->accessToken)
            ->postJson(route('api.discord.members.resolve', '999999999999999999'))
            ->assertOk()
            ->assertExactJson(['status' => 'ready']);
    }

    #[Test]
    public function it_clears_the_api_token_cookie_on_logout(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertCookieExpired(Passport::cookie());
    }

    #[Test]
    public function it_issues_a_fresh_api_token_cookie_from_the_refresh_endpoint(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson(route('auth.api-token'))
            ->assertNoContent()
            ->assertCookie(Passport::cookie());
    }

    #[Test]
    #[Group('authorization')]
    public function it_returns_401_from_the_refresh_endpoint_for_guests(): void
    {
        $this->getJson(route('auth.api-token'))
            ->assertUnauthorized()
            ->assertCookieMissing(Passport::cookie());
    }

    // ==================== helpers ====================

    private function officerWhoCanResolveDiscordMembers(): User
    {
        DiscordRole::factory()->officer()->create()
            ->givePermissionTo(Permission::firstOrCreate(['name' => 'create-planned-absences', 'guard_name' => 'web']));

        return User::factory()->officer()->create();
    }

    private function apiTokenCookie(User $user, string $csrfToken): string
    {
        return app(ApiTokenCookieFactory::class)->make($user->getKey(), $csrfToken)->getValue();
    }
}
