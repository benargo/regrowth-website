<?php

namespace Tests\Unit\Policies;

use App\Models\RecruitmentMacro;
use App\Models\User;
use App\Policies\RecruitmentMacroPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('recruitment')]
class RecruitmentMacroPolicyTest extends TestCase
{
    use RefreshDatabase;

    private RecruitmentMacroPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new RecruitmentMacroPolicy;
    }

    #[Test]
    public function viewers_can_view_any_but_not_change_macros(): void
    {
        $viewer = User::factory()->withPermissions('view-recruitment-macros')->create();
        $macro = RecruitmentMacro::factory()->for($viewer, 'createdBy')->create();

        $this->assertTrue($this->policy->viewAny($viewer));
        $this->assertFalse($this->policy->create($viewer));
        $this->assertFalse($this->policy->update($viewer, $macro));
        $this->assertFalse($this->policy->reorder($viewer));
        $this->assertFalse($this->policy->delete($viewer, $macro));
        $this->assertFalse($this->policy->restore($viewer, $macro));
    }

    #[Test]
    public function managers_can_create_reorder_and_update_anyones_macro(): void
    {
        $manager = User::factory()->withPermissions('manage-recruitment-macros')->create();
        $macro = RecruitmentMacro::factory()->create();

        $this->assertTrue($this->policy->create($manager));
        $this->assertTrue($this->policy->reorder($manager));
        $this->assertTrue($this->policy->update($manager, $macro));
    }

    #[Test]
    public function the_creator_can_delete_and_restore_their_own_macro(): void
    {
        $creator = User::factory()->withPermissions('manage-recruitment-macros')->create();
        $macro = RecruitmentMacro::factory()->for($creator, 'createdBy')->create();

        $this->assertTrue($this->policy->delete($creator, $macro));
        $this->assertTrue($this->policy->restore($creator, $macro));
    }

    #[Test]
    public function another_manager_cannot_delete_or_restore_someone_elses_macro(): void
    {
        $manager = User::factory()->withPermissions('manage-recruitment-macros')->create();
        $macro = RecruitmentMacro::factory()->create();

        $this->assertFalse($this->policy->delete($manager, $macro));
        $this->assertFalse($this->policy->restore($manager, $macro));
    }

    #[Test]
    public function the_creator_cannot_delete_their_macro_without_the_manage_permission(): void
    {
        $creator = User::factory()->withPermissions('view-recruitment-macros')->create();
        $macro = RecruitmentMacro::factory()->for($creator, 'createdBy')->create();

        $this->assertFalse($this->policy->delete($creator, $macro));
    }

    #[Test]
    public function delete_any_holders_can_delete_and_restore_anyones_macro(): void
    {
        $officer = User::factory()->withPermissions('delete-any-recruitment-macro')->create();
        $macro = RecruitmentMacro::factory()->create();
        $orphan = RecruitmentMacro::factory()->create(['created_by' => null]);

        $this->assertTrue($this->policy->delete($officer, $macro));
        $this->assertTrue($this->policy->restore($officer, $macro));
        $this->assertTrue($this->policy->delete($officer, $orphan));
    }

    #[Test]
    public function nobody_but_delete_any_holders_can_delete_a_macro_without_a_creator(): void
    {
        $manager = User::factory()->withPermissions('manage-recruitment-macros')->create();
        $orphan = RecruitmentMacro::factory()->create(['created_by' => null]);

        $this->assertFalse($this->policy->delete($manager, $orphan));
    }

    #[Test]
    public function users_without_permissions_are_denied_everything(): void
    {
        $user = User::factory()->create();
        $macro = RecruitmentMacro::factory()->for($user, 'createdBy')->create();

        $this->assertFalse($this->policy->viewAny($user));
        $this->assertFalse($this->policy->create($user));
        $this->assertFalse($this->policy->reorder($user));
        $this->assertFalse($this->policy->update($user, $macro));
        $this->assertFalse($this->policy->delete($user, $macro));
        $this->assertFalse($this->policy->restore($user, $macro));
    }
}
