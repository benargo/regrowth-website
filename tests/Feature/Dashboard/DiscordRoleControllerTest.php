<?php

namespace Tests\Feature\Dashboard;

use App\Models\DiscordRole;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('auth')]
class DiscordRoleControllerTest extends DashboardTestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->officer()->admin()->create();
    }

    #[Test]
    public function index_lists_every_role_in_position_order_for_admins(): void
    {
        DiscordRole::query()->delete();
        $low = DiscordRole::factory()->create(['position' => 1]);
        $high = DiscordRole::factory()->create(['position' => 3]);
        $middle = DiscordRole::factory()->create(['position' => 2]);

        $response = $this->actingAs($this->admin)->get(route('management.discord-roles.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/DiscordRoles/Index')
            ->where('discordRoles.data.0.id', $high->id)
            ->where('discordRoles.data.1.id', $middle->id)
            ->where('discordRoles.data.2.id', $low->id)
            ->has('discordRoles.data.0.users_count')
            ->has('discordRoles.data.0.permissions_count')
        );
    }

    #[Group('authorization')]
    #[Test]
    public function index_forbids_officers_who_are_not_admins(): void
    {
        $this->actingAs($this->officer)->get(route('management.discord-roles.index'))->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function update_forbids_officers_who_are_not_admins(): void
    {
        $role = DiscordRole::factory()->create();

        $this->actingAs($this->officer)
            ->patch(route('management.discord-roles.update', $role), ['is_visible' => true])
            ->assertForbidden();
    }

    #[Test]
    public function update_toggles_grants_permissions(): void
    {
        $role = DiscordRole::factory()->create(['grants_permissions' => false]);

        $response = $this->actingAs($this->admin)
            ->from(route('management.discord-roles.index'))
            ->patch(route('management.discord-roles.update', $role), ['grants_permissions' => true]);

        $response->assertRedirect(route('management.discord-roles.index'));
        $this->assertTrue($role->fresh()->grants_permissions);
    }

    #[Test]
    public function update_toggles_is_visible(): void
    {
        $role = DiscordRole::factory()->create(['is_visible' => false]);

        $response = $this->actingAs($this->admin)
            ->from(route('management.discord-roles.index'))
            ->patch(route('management.discord-roles.update', $role), ['is_visible' => true]);

        $response->assertRedirect(route('management.discord-roles.index'));
        $this->assertTrue($role->fresh()->is_visible);
    }

    #[Test]
    public function update_ignores_id_name_and_position(): void
    {
        $role = DiscordRole::factory()->create(['is_visible' => false, 'name' => 'Original', 'position' => 4]);

        $this->actingAs($this->admin)->patch(route('management.discord-roles.update', $role), [
            'is_visible' => true,
            'id' => '999',
            'name' => 'Changed',
            'position' => 9,
        ]);

        $role->refresh();
        $this->assertTrue($role->is_visible);
        $this->assertSame('Original', $role->name);
        $this->assertSame(4, $role->position);
    }

    #[Group('validation')]
    #[Test]
    public function update_requires_at_least_one_flag(): void
    {
        $role = DiscordRole::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('management.discord-roles.update', $role), [])
            ->assertSessionHasErrors(['grants_permissions', 'is_visible']);
    }

    #[Group('validation')]
    #[Test]
    public function update_rejects_non_boolean_flags(): void
    {
        $role = DiscordRole::factory()->create();

        $this->actingAs($this->admin)
            ->patch(route('management.discord-roles.update', $role), ['is_visible' => 'yes'])
            ->assertSessionHasErrors('is_visible');
    }
}
