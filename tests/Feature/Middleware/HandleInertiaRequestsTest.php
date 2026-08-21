<?php

namespace Tests\Feature\Middleware;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('platform')]
class HandleInertiaRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $viewOfficerDashboard = Permission::firstOrCreate(['name' => 'view-officer-dashboard', 'guard_name' => 'web']);
        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 6, 'is_visible' => true]);
        $officerRole->givePermissionTo($viewOfficerDashboard);
        DiscordRole::firstOrCreate(['id' => '1467994755953852590'], ['name' => 'Loot Councillor', 'position' => 5, 'is_visible' => true]);
    }

    #[Test]
    public function it_shares_view_officer_dashboard_permission_for_officers(): void
    {
        $user = User::factory()->officer()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.permissions', fn ($permissions) => $permissions->contains('view-officer-dashboard'))
            );
    }

    #[Test]
    public function it_does_not_share_view_officer_dashboard_permission_for_raiders(): void
    {
        $user = User::factory()->raider()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.permissions', fn ($permissions) => ! $permissions->contains('view-officer-dashboard'))
            );
    }

    #[Test]
    public function it_does_not_share_view_officer_dashboard_permission_for_members(): void
    {
        $user = User::factory()->member()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.permissions', fn ($permissions) => ! $permissions->contains('view-officer-dashboard'))
            );
    }

    #[Test]
    public function it_shares_empty_permissions_for_guests(): void
    {
        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.permissions', fn ($permissions) => $permissions->isEmpty())
            );
    }

    // ==================== user data sharing ====================

    #[Test]
    public function it_shares_user_data_with_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->member()->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
            'nickname' => 'TestNick',
            'avatar' => 'abc123',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('auth.user')
                ->where('auth.user.id', '123456789012345678')
                ->where('auth.user.username', 'testuser')
                ->where('auth.user.nickname', 'TestNick')
                ->where('auth.user.display_name', 'TestNick')
                ->where('auth.user.highest_role', 'Member')
            );
    }

    #[Test]
    public function it_shares_display_name_as_username_when_nickname_is_null(): void
    {
        $user = User::factory()->member()->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
            'nickname' => null,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.display_name', 'testuser')
            );
    }

    #[Test]
    public function it_shares_null_user_for_guest(): void
    {
        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user', null)
            );
    }

    #[Test]
    public function it_shares_avatar_url_for_authenticated_user(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
            'guild_avatar' => 'avatarhash123',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where(
                'auth.user.avatar',
                'https://cdn.discordapp.com/guilds/'.config('services.discord.server_id').'/users/123456789012345678/avatars/avatarhash123.webp'
            )
            );
    }

    #[Test]
    public function it_shares_highest_role_for_officer(): void
    {
        $officer = DiscordRole::find('829021769448816691') ??
            DiscordRole::factory()->officer()->create();
        $member = DiscordRole::find('829022020301094922') ??
            DiscordRole::factory()->member()->create();

        $user = User::factory()->withRoles([$officer->id, $member->id])->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.highest_role', 'Officer')
            );
    }

    #[Test]
    public function it_shares_null_highest_role_when_no_recognized_roles(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.highest_role', null)
            );
    }

    // ==================== phases scoping ====================

    #[Test]
    public function shared_props_do_not_include_phases(): void
    {
        Phase::factory()->create();
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('loot.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->missing('phases'));
    }
}
