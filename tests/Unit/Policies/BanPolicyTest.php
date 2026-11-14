<?php

namespace Tests\Unit\Policies;

use App\Models\DiscordRole;
use App\Models\GuildRosterManager\Ban;
use App\Models\User;
use App\Policies\BanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

#[Group('characters')]
#[Group('auth')]
class BanPolicyTest extends TestCase
{
    use RefreshDatabase;

    private BanPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new BanPolicy;
    }

    #[Test]
    public function view_any_and_view_require_view_grm_bans(): void
    {
        $ban = new Ban;
        $allowed = $this->userWithPermissions(['view-grm-bans']);
        $denied = $this->userWithPermissions([]);

        $this->assertTrue($this->policy->viewAny($allowed));
        $this->assertTrue($this->policy->view($allowed, $ban));
        $this->assertFalse($this->policy->viewAny($denied));
        $this->assertFalse($this->policy->view($denied, $ban));
    }

    #[Test]
    public function view_reason_requires_view_grm_ban_reasons(): void
    {
        $ban = new Ban;

        $this->assertFalse($this->policy->viewReason($this->userWithPermissions(['view-grm-bans']), $ban));
        $this->assertTrue($this->policy->viewReason($this->userWithPermissions(['view-grm-ban-reasons']), $ban));
    }

    #[Test]
    public function create_requires_create_grm_bans(): void
    {
        $this->assertTrue($this->policy->create($this->userWithPermissions(['create-grm-bans'])));
        $this->assertFalse($this->policy->create($this->userWithPermissions(['update-grm-bans'])));
    }

    #[Test]
    public function update_requires_update_grm_bans(): void
    {
        $allowed = $this->userWithPermissions(['update-grm-bans']);
        $denied = $this->userWithPermissions(['create-grm-bans']);

        $this->assertTrue($this->policy->update($allowed));
        $this->assertTrue($this->policy->update($allowed, new Ban));
        $this->assertFalse($this->policy->update($denied));
        $this->assertFalse($this->policy->update($denied, new Ban));
    }

    #[Test]
    public function delete_and_force_delete_are_always_false(): void
    {
        $user = $this->userWithPermissions(['view-grm-bans', 'view-grm-ban-reasons', 'create-grm-bans', 'update-grm-bans']);

        $this->assertFalse($this->policy->delete($user, new Ban));
        $this->assertFalse($this->policy->forceDelete($user, new Ban));
    }

    #[Test]
    public function view_officer_dashboard_grants_no_ban_ability(): void
    {
        $user = $this->userWithPermissions(['view-officer-dashboard']);

        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->viewReason($user, new Ban));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->update($user));
    }

    // ==================== helpers ====================

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
        }

        $user = User::factory()->create();
        $user->discordRoles()->attach($role->id);
        $user->load('discordRoles.permissions');

        return $user;
    }
}
