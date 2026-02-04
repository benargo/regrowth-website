<?php

namespace Tests\Feature\Middleware;

use App\Models\DiscordRole;
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
        $user = User::factory()->officer()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.can.accessDashboard', true)
            );
    }

    #[Test]
    public function it_shares_can_access_control_panel_as_false_for_raiders(): void
    {
        $user = User::factory()->raider()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.can.accessDashboard', false)
            );
    }

    #[Test]
    public function it_shares_can_access_control_panel_as_false_for_members(): void
    {
        $user = User::factory()->member()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.can.accessDashboard', false)
            );
    }

    #[Test]
    public function it_shares_can_access_control_panel_as_false_for_guests(): void
    {
        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.can.accessDashboard', false)
            );
    }

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
                'https://cdn.discordapp.com/guilds/'.config('services.discord.guild_id').'/users/123456789012345678/avatars/avatarhash123.webp'
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
}
