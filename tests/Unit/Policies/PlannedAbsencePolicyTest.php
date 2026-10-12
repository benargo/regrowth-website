<?php

namespace Tests\Unit\Policies;

use App\Models\DiscordRole;
use App\Models\PlannedAbsence;
use App\Models\User;
use App\Policies\PlannedAbsencePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

#[Group('auth')]
class PlannedAbsencePolicyTest extends TestCase
{
    use RefreshDatabase;

    private PlannedAbsencePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new PlannedAbsencePolicy;
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
        $user = $this->userWithPermission('view-planned-absences');

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
        $user = $this->userWithPermission('view-planned-absences');
        $absence = PlannedAbsence::factory()->create();

        $this->assertTrue($this->policy->view($user, $absence));
    }

    #[Test]
    public function it_allows_view_when_user_is_creator(): void
    {
        $user = $this->userWithoutPermission();
        $absence = PlannedAbsence::factory()->create(['created_by' => $user->id]);

        $this->assertTrue($this->policy->view($user, $absence));
    }

    #[Test]
    public function it_denies_view_without_permission_and_not_creator(): void
    {
        $user = $this->userWithoutPermission();
        $absence = PlannedAbsence::factory()->create();

        $this->assertFalse($this->policy->view($user, $absence));
    }

    // ==================== create ====================

    #[Test]
    public function it_allows_create_with_permission(): void
    {
        $user = $this->userWithPermission('create-planned-absences');

        $this->assertTrue($this->policy->create($user));
    }

    #[Test]
    public function it_allows_backdate_with_backdate_permission(): void
    {
        $user = $this->userWithPermission('backdate-planned-absences');

        $this->assertTrue($this->policy->backdate($user));
    }

    #[Test]
    public function it_denies_backdate_without_permission(): void
    {
        $user = $this->userWithoutPermission();

        $this->assertFalse($this->policy->backdate($user));
    }

    // ==================== update ====================

    #[Test]
    public function it_allows_update_for_the_creator_before_the_absence_starts(): void
    {
        $this->freezeTime();
        $user = $this->userWithoutPermission();
        $absence = PlannedAbsence::factory()->create(['created_by' => $user->id, 'start_date' => today()->addDay()]);

        $this->assertTrue($this->policy->update($user, $absence));
    }

    #[Test]
    public function it_allows_update_for_the_creator_on_the_start_date(): void
    {
        $this->freezeTime();
        $user = $this->userWithoutPermission();
        $absence = PlannedAbsence::factory()->create(['created_by' => $user->id, 'start_date' => today()]);

        $this->assertTrue($this->policy->update($user, $absence));
    }

    #[Test]
    public function it_denies_update_for_the_creator_once_the_absence_has_started(): void
    {
        $this->freezeTime();
        $user = $this->userWithoutPermission();
        $absence = PlannedAbsence::factory()->create(['created_by' => $user->id, 'start_date' => today()->subDay()]);

        $this->assertFalse($this->policy->update($user, $absence));
    }

    #[Test]
    public function it_allows_update_for_a_non_creator_with_update_permission(): void
    {
        $this->freezeTime();
        $user = $this->userWithPermission('update-planned-absences');
        $absence = PlannedAbsence::factory()->create(['start_date' => today()->addDay()]);

        $this->assertTrue($this->policy->update($user, $absence));
    }

    #[Test]
    public function it_allows_update_with_permission_after_the_absence_has_started(): void
    {
        $this->freezeTime();
        $user = $this->userWithPermission('update-planned-absences');
        $absence = PlannedAbsence::factory()->create(['start_date' => today()->subDay()]);

        $this->assertTrue($this->policy->update($user, $absence));
    }

    #[Test]
    public function it_denies_update_for_a_non_creator_without_permission(): void
    {
        $this->freezeTime();
        $user = $this->userWithoutPermission();
        $absence = PlannedAbsence::factory()->create(['start_date' => today()->addDay()]);

        $this->assertFalse($this->policy->update($user, $absence));
    }

    // ==================== delete ====================

    #[Test]
    public function it_allows_delete_for_the_creator_before_the_absence_starts(): void
    {
        $this->freezeTime();
        $user = $this->userWithoutPermission();
        $absence = PlannedAbsence::factory()->create(['created_by' => $user->id, 'start_date' => today()->addDay()]);

        $this->assertTrue($this->policy->delete($user, $absence));
    }

    #[Test]
    public function it_allows_delete_for_the_creator_on_the_start_date(): void
    {
        $this->freezeTime();
        $user = $this->userWithoutPermission();
        $absence = PlannedAbsence::factory()->create(['created_by' => $user->id, 'start_date' => today()]);

        $this->assertTrue($this->policy->delete($user, $absence));
    }

    #[Test]
    public function it_denies_delete_for_the_creator_once_the_absence_has_started(): void
    {
        $this->freezeTime();
        $user = $this->userWithoutPermission();
        $absence = PlannedAbsence::factory()->create(['created_by' => $user->id, 'start_date' => today()->subDay()]);

        $this->assertFalse($this->policy->delete($user, $absence));
    }

    #[Test]
    public function it_allows_delete_for_a_non_creator_with_delete_permission(): void
    {
        $this->freezeTime();
        $user = $this->userWithPermission('delete-planned-absences');
        $absence = PlannedAbsence::factory()->create(['start_date' => today()->addDay()]);

        $this->assertTrue($this->policy->delete($user, $absence));
    }

    #[Test]
    public function it_allows_delete_with_permission_after_the_absence_has_started(): void
    {
        $this->freezeTime();
        $user = $this->userWithPermission('delete-planned-absences');
        $absence = PlannedAbsence::factory()->create(['start_date' => today()->subDay()]);

        $this->assertTrue($this->policy->delete($user, $absence));
    }

    #[Test]
    public function it_denies_delete_for_a_non_creator_without_permission(): void
    {
        $this->freezeTime();
        $user = $this->userWithoutPermission();
        $absence = PlannedAbsence::factory()->create(['start_date' => today()->addDay()]);

        $this->assertFalse($this->policy->delete($user, $absence));
    }

    // ==================== restore ====================

    #[Test]
    public function it_allows_restore_for_admins(): void
    {
        $user = User::factory()->admin()->create();
        $absence = PlannedAbsence::factory()->create();

        $this->assertTrue($this->policy->restore($user, $absence));
    }

    #[Test]
    public function it_denies_restore_for_non_admins(): void
    {
        $user = User::factory()->create();
        $absence = PlannedAbsence::factory()->create();

        $this->assertFalse($this->policy->restore($user, $absence));
    }

    // ==================== force delete ====================

    #[Test]
    public function it_allows_force_delete_for_admins(): void
    {
        $user = User::factory()->admin()->create();
        $absence = PlannedAbsence::factory()->create();

        $this->assertTrue($this->policy->forceDelete($user, $absence));
    }

    #[Test]
    public function it_denies_force_delete_for_non_admins(): void
    {
        $user = User::factory()->create();
        $absence = PlannedAbsence::factory()->create();

        $this->assertFalse($this->policy->forceDelete($user, $absence));
    }
}
