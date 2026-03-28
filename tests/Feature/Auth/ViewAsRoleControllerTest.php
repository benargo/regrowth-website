<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\ViewAsRoleController;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ViewAsRoleControllerTest extends TestCase
{
    use RefreshDatabase;

    // ==================== viewAsRole: Access Control ====================

    #[Test]
    public function view_as_role_requires_authentication(): void
    {
        $role = DiscordRole::factory()->raider()->create();

        $response = $this->get(route('auth.view-as', $role->id));

        $response->assertRedirect('/login');
    }

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
    public function view_as_role_impersonates_test_raider(): void
    {
        $user = User::factory()->withPermissions('impersonate-roles')->create();
        $raiderRole = DiscordRole::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('auth.view-as', $raiderRole->id));

        $response->assertRedirect('/');
        $response->assertSessionHas('impersonating_user_id', $user->id);

        $this->assertAuthenticatedAs(User::find(ViewAsRoleController::TEST_RAIDER_ID));
    }

    #[Test]
    public function view_as_raider_also_syncs_member_role(): void
    {
        $user = User::factory()->withPermissions('impersonate-roles')->create();
        $raiderRole = DiscordRole::factory()->raider()->create();
        $memberRole = DiscordRole::factory()->member()->create();

        $this->actingAs($user)->get(route('auth.view-as', $raiderRole->id));

        $testUser = User::find(ViewAsRoleController::TEST_RAIDER_ID);
        $roleIds = $testUser->discordRoles->pluck('id')->all();

        $this->assertContains($raiderRole->id, $roleIds);
        $this->assertContains($memberRole->id, $roleIds);
    }

    #[Test]
    public function view_as_member_does_not_sync_extra_roles(): void
    {
        $user = User::factory()->withPermissions('impersonate-roles')->create();
        $memberRole = DiscordRole::factory()->member()->create();

        $this->actingAs($user)->get(route('auth.view-as', $memberRole->id));

        $testUser = User::find(ViewAsRoleController::TEST_MEMBER_ID);

        $this->assertCount(1, $testUser->discordRoles);
        $this->assertTrue($testUser->discordRoles->first()->is($memberRole));
    }

    #[Test]
    public function view_as_guest_creates_test_guest(): void
    {
        $user = User::factory()->withPermissions('impersonate-roles')->create();
        $guestRole = DiscordRole::factory()->guest()->create();

        $this->actingAs($user)->get(route('auth.view-as', $guestRole->id));

        $testUser = User::find(ViewAsRoleController::TEST_GUEST_ID);

        $this->assertNotNull($testUser);
        $this->assertSame('Test Guest', $testUser->nickname);
    }

    #[Test]
    public function view_as_role_with_invalid_role_redirects_with_error(): void
    {
        $user = User::factory()->withPermissions('impersonate-roles')->create();

        $response = $this->actingAs($user)->get(route('auth.view-as', '999999'));

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

        $response->assertRedirect(route('dashboard.index'));
        $response->assertSessionHas('success');

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
