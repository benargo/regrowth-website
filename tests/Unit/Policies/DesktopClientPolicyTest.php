<?php

namespace Tests\Unit\Policies;

use App\Models\DesktopClient;
use App\Models\DiscordRole;
use App\Models\User;
use App\Policies\DesktopClientPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

#[Group('auth')]
class DesktopClientPolicyTest extends TestCase
{
    use RefreshDatabase;

    private DesktopClientPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new DesktopClientPolicy;
    }

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

    #[Test]
    public function view_any_and_create_require_use_desktop_client(): void
    {
        $allowed = $this->userWithPermissions(['use-desktop-client']);
        $denied = $this->userWithPermissions([]);

        $this->assertTrue($this->policy->viewAny($allowed));
        $this->assertTrue($this->policy->create($allowed));
        $this->assertFalse($this->policy->viewAny($denied));
        $this->assertFalse($this->policy->create($denied));
    }

    #[Test]
    public function upload_guild_roster_requires_both_permissions(): void
    {
        $this->assertFalse($this->policy->uploadGuildRoster($this->userWithPermissions(['use-desktop-client'])));
        $this->assertFalse($this->policy->uploadGuildRoster($this->userWithPermissions(['upload-grm-rosters'])));
        $this->assertTrue($this->policy->uploadGuildRoster($this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters'])));
    }

    #[Test]
    public function delete_allows_the_owner_without_any_permission(): void
    {
        $user = $this->userWithPermissions([]);
        $desktopClient = DesktopClient::factory()->for($user)->create();

        $this->assertTrue($this->policy->delete($user, $desktopClient));
    }

    #[Test]
    public function delete_allows_revoke_any_desktop_client_on_another_users_client(): void
    {
        $user = $this->userWithPermissions(['revoke-any-desktop-client']);
        $desktopClient = DesktopClient::factory()->create();

        $this->assertTrue($this->policy->delete($user, $desktopClient));
    }

    #[Test]
    public function delete_denies_another_users_client(): void
    {
        $user = $this->userWithPermissions(['use-desktop-client']);
        $desktopClient = DesktopClient::factory()->create();

        $this->assertFalse($this->policy->delete($user, $desktopClient));
    }
}
