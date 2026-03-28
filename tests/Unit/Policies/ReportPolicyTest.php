<?php

namespace Tests\Unit\Policies;

use App\Models\DiscordRole;
use App\Models\User;
use App\Models\WarcraftLogs\Report;
use App\Policies\ReportPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReportPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ReportPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ReportPolicy;
    }

    private function userWithPermission(string $permission): User
    {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);

        $role = DiscordRole::factory()->create();
        $role->givePermissionTo($permission);

        $user = User::factory()->create();
        $user->discordRoles()->attach($role->id);
        $user->load('discordRoles.permissions');

        return $user;
    }

    private function userWithoutPermission(): User
    {
        $user = User::factory()->create();
        $user->load('discordRoles.permissions');

        return $user;
    }

    #[Test]
    public function it_allows_view_any_with_permission(): void
    {
        $user = $this->userWithPermission('view-reports');

        $this->assertTrue($this->policy->viewAny($user));
    }

    #[Test]
    public function it_denies_view_any_without_permission(): void
    {
        $user = $this->userWithoutPermission();

        $this->assertFalse($this->policy->viewAny($user));
    }

    #[Test]
    public function it_allows_view_with_permission(): void
    {
        $user = $this->userWithPermission('view-reports');
        $report = Report::factory()->create();

        $this->assertTrue($this->policy->view($user, $report));
    }

    #[Test]
    public function it_allows_create_with_manage_reports_permission(): void
    {
        $user = $this->userWithPermission('manage-reports');

        $this->assertTrue($this->policy->create($user));
    }

    #[Test]
    public function it_denies_create_without_permission(): void
    {
        $user = $this->userWithoutPermission();

        $this->assertFalse($this->policy->create($user));
    }

    #[Test]
    public function it_allows_update_with_manage_reports_permission(): void
    {
        $user = $this->userWithPermission('manage-reports');
        $report = Report::factory()->create();

        $this->assertTrue($this->policy->update($user, $report));
    }

    #[Test]
    public function it_allows_delete_with_manage_reports_permission(): void
    {
        $user = $this->userWithPermission('manage-reports');
        $report = Report::factory()->create();

        $this->assertTrue($this->policy->delete($user, $report));
    }

    #[Test]
    public function it_denies_delete_without_permission(): void
    {
        $user = $this->userWithoutPermission();
        $report = Report::factory()->create();

        $this->assertFalse($this->policy->delete($user, $report));
    }
}
