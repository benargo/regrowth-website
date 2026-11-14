<?php

namespace Tests\Unit\Providers;

use App\Http\Integrations\ServedFromCache;
use App\Jobs\FetchGuildRoster;
use App\Models\DesktopClient;
use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('platform')]
class AppServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    // ==================== whereNone macro ====================

    #[Test]
    public function it_registers_the_where_none_macro_on_the_eloquent_builder(): void
    {
        $this->assertTrue(method_exists(Report::query(), 'whereNone') || is_callable([Report::query(), 'whereNone']));
    }

    #[Test]
    public function where_none_macro_returns_no_rows(): void
    {
        Report::factory()->count(3)->create();

        $results = Report::query()->whereNone()->get();

        $this->assertCount(0, $results);
    }

    // ==================== container bindings ====================

    #[Test]
    public function it_binds_served_from_cache_as_a_singleton(): void
    {
        $this->assertTrue($this->app->isShared(ServedFromCache::class));
        $this->assertSame($this->app->make(ServedFromCache::class), $this->app->make(ServedFromCache::class));
    }

    // ==================== authorization gates ====================

    #[Test]
    public function it_defines_the_view_priorities_page_gate(): void
    {
        $this->assertTrue(Gate::has('view-priorities-page'));
    }

    #[Test]
    public function it_grants_the_view_priorities_page_gate_to_authorized_users(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'view-priorities-page', 'guard_name' => 'web']);
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5, 'is_visible' => true, 'grants_permissions' => true]
        );
        $officerRole->givePermissionTo($permission);

        $user = User::factory()->officer()->create();

        $this->assertTrue(Gate::forUser($user)->allows('view-priorities-page'));
    }

    #[Test]
    public function it_denies_the_view_priorities_page_gate_to_unauthorized_users(): void
    {
        $user = User::factory()->member()->create();

        $this->assertFalse(Gate::forUser($user)->allows('view-priorities-page'));
    }

    // ==================== rate limiting ====================

    #[Test]
    public function it_rate_limits_fetch_guild_roster_per_game_version(): void
    {
        $limiter = RateLimiter::limiter('fetch-guild-roster-job');

        $this->assertNotSame(
            $limiter(new FetchGuildRoster(1))->key,
            $limiter(new FetchGuildRoster(2))->key,
        );
    }

    #[Test]
    public function it_registers_the_grm_sync_rate_limiters(): void
    {
        $this->assertNotNull(RateLimiter::limiter('grm-syncs'));
        $this->assertNotNull(RateLimiter::limiter('grm-sync-requests'));
        $this->assertNull(RateLimiter::limiter('grm-uploads'));
    }

    #[Test]
    #[Group('deprecated')]
    public function it_no_longer_registers_the_grm_upload_route(): void
    {
        $this->assertFalse(Route::has('api.grm.uploads.store'));
    }

    // ==================== passport ====================

    #[Test]
    public function it_registers_only_the_authorization_code_oauth_routes(): void
    {
        foreach ([
            'passport.token',
            'passport.authorizations.authorize',
            'passport.authorizations.approve',
            'passport.authorizations.deny',
        ] as $name) {
            $this->assertTrue(Route::has($name), $name);
        }

        foreach ([
            'passport.device',
            'passport.device.code',
            'passport.device.authorizations.authorize',
            'passport.device.authorizations.approve',
            'passport.device.authorizations.deny',
            'passport.token.refresh',
            'passport.tokens.index',
            'link',
        ] as $name) {
            $this->assertFalse(Route::has($name), $name);
        }
    }

    #[Test]
    public function it_registers_the_token_routes_without_csrf(): void
    {
        $middleware = Route::getRoutes()->getByName('passport.token')->gatherMiddleware();

        $this->assertNotContains('web', $middleware);
        $this->assertNotContains(ValidateCsrfToken::class, $middleware);
    }

    #[Test]
    public function it_puts_the_consent_routes_behind_auth_and_the_desktop_client_policy(): void
    {
        foreach ([
            'passport.authorizations.authorize',
            'passport.authorizations.approve',
            'passport.authorizations.deny',
        ] as $name) {
            $middleware = Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? [];

            $this->assertContains('web', $middleware, $name);
            $this->assertContains('auth', $middleware, $name);
            $this->assertContains('can:create,'.DesktopClient::class, $middleware, $name);
        }
    }

    #[Test]
    public function it_registers_the_grm_scopes(): void
    {
        $this->assertTrue(Passport::hasScope('grm:upload'));
        $this->assertTrue(Passport::hasScope('grm:targets'));
    }

    #[Test]
    public function it_sets_token_lifetimes(): void
    {
        $this->assertSame(
            3600.0,
            round(CarbonInterval::instance(Passport::tokensExpireIn())->totalSeconds),
        );
        $this->assertSame(
            7776000.0,
            round(CarbonInterval::instance(Passport::refreshTokensExpireIn())->totalSeconds),
        );
    }
}
