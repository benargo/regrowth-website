<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HandleInertiaRequestsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shares_can_access_control_panel_as_true_for_officers(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345678',
            'username' => 'officer_user',
            'discriminator' => '0',
            'roles' => ['829021769448816691'], // Officer role
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canAccessDashboard', true)
            );
    }

    #[Test]
    public function it_shares_can_access_control_panel_as_false_for_raiders(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345679',
            'username' => 'raider_user',
            'discriminator' => '0',
            'roles' => ['1265247017215594496'], // Raider role
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canAccessDashboard', false)
            );
    }

    #[Test]
    public function it_shares_can_access_control_panel_as_false_for_members(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345680',
            'username' => 'member_user',
            'discriminator' => '0',
            'roles' => ['829022020301094922'], // Member role
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canAccessDashboard', false)
            );
    }

    #[Test]
    public function it_shares_can_access_control_panel_as_false_for_guests(): void
    {
        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('canAccessDashboard', false)
            );
    }

    #[Test]
    public function it_shares_user_data_with_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
            'nickname' => 'TestNick',
            'avatar' => 'abc123',
            'roles' => ['829022020301094922'],
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
        $user = User::factory()->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
            'nickname' => null,
            'roles' => ['829022020301094922'],
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
            'roles' => [],
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where(
                'auth.user.avatar_url',
                'https://cdn.discordapp.com/guilds/'.config('services.discord.guild_id').'/users/123456789012345678/avatars/avatarhash123.webp'
            )
            );
    }

    #[Test]
    public function it_shares_highest_role_for_officer(): void
    {
        $user = User::factory()->create([
            'id' => '123456789012345678',
            'username' => 'testuser',
            'discriminator' => '0',
            'roles' => ['829021769448816691', '829022020301094922'], // Officer + Member
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
            'roles' => ['999999999999999999'], // Unknown role
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.highest_role', null)
            );
    }
}
