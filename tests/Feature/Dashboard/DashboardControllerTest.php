<?php

namespace Tests\Feature\Dashboard;

use App\Models\DiscordRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('dashboard')]
class DashboardControllerTest extends DashboardTestCase
{
    // ==================== access control ====================

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get(route('management.dashboard'))
            ->assertRedirect('/login');
    }

    #[Test]
    public function users_without_the_permission_are_forbidden(): void
    {
        $user = User::factory()->raider()->create();

        $this->actingAs($user)
            ->get(route('management.dashboard'))
            ->assertForbidden();
    }

    #[Test]
    public function officers_can_view_the_dashboard(): void
    {
        $this->actingAs($this->officer)
            ->get(route('management.dashboard'))
            ->assertOk();
    }

    // ==================== inertia response ====================

    #[Test]
    public function it_renders_the_dashboard_component(): void
    {
        $this->actingAs($this->officer)
            ->get(route('management.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->component('Manage/Dashboard'));
    }

    #[Test]
    public function it_shares_discord_role_ids(): void
    {
        $raider = DiscordRole::factory()->create(['name' => 'Raider']);
        $member = DiscordRole::factory()->create(['name' => 'Member']);
        $guest = DiscordRole::factory()->create(['name' => 'Guest']);

        $this->actingAs($this->officer)
            ->get(route('management.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('discordRoles.raider', $raider->id)
                ->where('discordRoles.member', $member->id)
                ->where('discordRoles.guest', $guest->id)
                ->etc()
            );
    }

    #[Test]
    public function it_renders_with_the_forever_theme(): void
    {
        $this->actingAs($this->officer)
            ->get(route('management.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('theme', 'forever')->etc());
    }
}
