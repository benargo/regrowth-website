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
    public function it_passes_permission_granting_roles_in_position_order(): void
    {
        $low = DiscordRole::factory()->grantsPermissions()->create(['position' => 99998]);
        $high = DiscordRole::factory()->grantsPermissions()->create(['position' => 99999]);
        $nonGranting = DiscordRole::factory()->create(['position' => 99997]);

        $this->actingAs($this->officer)
            ->get(route('management.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('discordRoles.0.id', $high->id)
                ->where('discordRoles.0.name', $high->name)
                ->where('discordRoles.1.id', $low->id)
                ->where('discordRoles', fn ($roles) => collect($roles)->pluck('id')->doesntContain($nonGranting->id))
                ->etc()
            );
    }
}
