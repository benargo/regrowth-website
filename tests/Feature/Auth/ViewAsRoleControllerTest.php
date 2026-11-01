<?php

namespace Tests\Feature\Auth;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('auth')]
class ViewAsRoleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.local_users.view_as' => '100000000000000099']);
    }

    // ==================== viewAsRole: Access Control ====================

    #[Test]
    public function view_as_role_requires_authentication(): void
    {
        $role = DiscordRole::factory()->raider()->create();

        $response = $this->get(route('auth.view-as', $role->id));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function view_as_role_requires_impersonate_permission(): void
    {
        $user = User::factory()->member()->create();
        $role = DiscordRole::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('auth.view-as', $role->id));

        $response->assertForbidden();
    }

    // ==================== viewAsRole: Impersonation ====================

    #[Test]
    public function view_as_role_impersonates_the_shared_test_user_with_only_the_chosen_role(): void
    {
        $user = User::factory()->admin()->create();
        $role = DiscordRole::factory()->grantsPermissions()->create();

        $response = $this->actingAs($user)->get(route('auth.view-as', $role->id));

        $response->assertRedirect('/');
        $response->assertSessionHas('impersonating_user_id', $user->id);

        $testUser = User::find('100000000000000099');
        $this->assertAuthenticatedAs($testUser);
        $this->assertSame([$role->id], $testUser->discordRoles()->pluck('discord_roles.id')->all());
    }

    #[Test]
    public function view_as_role_replaces_roles_from_a_previous_view_as(): void
    {
        $user = User::factory()->admin()->create();
        $previous = DiscordRole::factory()->grantsPermissions()->create();
        $role = DiscordRole::factory()->grantsPermissions()->create();
        $testUser = User::factory()->create(['id' => '100000000000000099']);
        $testUser->discordRoles()->sync([$previous->id]);

        $this->actingAs($user)->get(route('auth.view-as', $role->id));

        $this->assertSame([$role->id], $testUser->discordRoles()->pluck('discord_roles.id')->all());
    }

    #[Test]
    public function view_as_role_rejects_a_role_that_does_not_grant_permissions(): void
    {
        $user = User::factory()->admin()->create();
        $role = DiscordRole::factory()->create();

        $response = $this->actingAs($user)->get(route('auth.view-as', $role->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertNull(User::find('100000000000000099'));
    }

    #[Test]
    public function view_as_role_with_invalid_role_redirects_with_error(): void
    {
        $user = User::factory()->withPermissions('impersonate-roles')->create();

        $response = $this->actingAs($user)->get(route('auth.view-as', '999999'));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function view_as_role_errors_when_local_user_id_is_not_configured(): void
    {
        config()->set('auth.local_users.view_as', null);

        $user = User::factory()->admin()->create();
        $role = DiscordRole::factory()->grantsPermissions()->create();

        $response = $this->actingAs($user)->get(route('auth.view-as', $role->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ==================== stopViewingAs ====================

    #[Test]
    public function stop_viewing_as_restores_original_user(): void
    {
        $originalUser = User::factory()->withPermissions('impersonate-roles')->create();
        $raiderRole = DiscordRole::factory()->raider()->create();

        // Start impersonation
        $this->actingAs($originalUser)->get(route('auth.view-as', $raiderRole->id));

        // Stop impersonation
        $response = $this->get(route('auth.return-to-self'));

        $response->assertRedirect(route('management.dashboard'));
        $response->assertSessionHas('success');

        $this->assertAuthenticatedAs($originalUser);
    }

    #[Test]
    public function stop_viewing_as_after_a_nested_view_as_restores_the_original_user(): void
    {
        $originalUser = User::factory()->admin()->create();
        $roleA = DiscordRole::factory()->grantsPermissions()->create();
        $roleA->givePermissionTo(Permission::firstOrCreate(['name' => 'impersonate-roles', 'guard_name' => 'web']));
        $roleB = DiscordRole::factory()->grantsPermissions()->create();

        $this->actingAs($originalUser)->get(route('auth.view-as', $roleA->id));
        $this->get(route('auth.view-as', $roleB->id));

        $this->get(route('auth.return-to-self'));

        $this->assertAuthenticatedAs($originalUser);
    }

    #[Test]
    public function stop_viewing_as_with_invalid_session_logs_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['impersonating_user_id' => 'nonexistent-id'])
            ->get(route('auth.return-to-self'));

        $response->assertRedirect('/');
        $response->assertSessionHas('error');
        $this->assertGuest();
    }
}
