<?php

namespace Tests\Unit\Policies;

use App\Models\DiscordRole;
use App\Models\LootCouncil\Item;
use App\Models\User;
use App\Policies\ItemPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ItemPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ItemPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ItemPolicy;
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
        $user = $this->userWithPermission('view-loot-bias-tool');

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
        $user = $this->userWithPermission('view-loot-bias-tool');
        $item = Item::factory()->create();

        $this->assertTrue($this->policy->view($user, $item));
    }

    #[Test]
    public function it_denies_view_without_permission(): void
    {
        $user = $this->userWithoutPermission();
        $item = Item::factory()->create();

        $this->assertFalse($this->policy->view($user, $item));
    }

    #[Test]
    public function it_always_denies_create(): void
    {
        $user = $this->userWithPermission('view-loot-bias-tool');

        $this->assertFalse($this->policy->create($user));
    }

    #[Test]
    public function it_allows_update_with_edit_items_permission(): void
    {
        $user = $this->userWithPermission('edit-items');
        $item = Item::factory()->create();

        $this->assertTrue($this->policy->update($user, $item));
    }

    #[Test]
    public function it_denies_update_without_permission(): void
    {
        $user = $this->userWithoutPermission();
        $item = Item::factory()->create();

        $this->assertFalse($this->policy->update($user, $item));
    }

    #[Test]
    public function it_always_denies_delete(): void
    {
        $user = $this->userWithPermission('edit-items');
        $item = Item::factory()->create();

        $this->assertFalse($this->policy->delete($user, $item));
    }

    #[Test]
    public function it_always_denies_restore(): void
    {
        $user = $this->userWithPermission('edit-items');
        $item = Item::factory()->create();

        $this->assertFalse($this->policy->restore($user, $item));
    }

    #[Test]
    public function it_always_denies_force_delete(): void
    {
        $user = $this->userWithPermission('edit-items');
        $item = Item::factory()->create();

        $this->assertFalse($this->policy->forceDelete($user, $item));
    }
}
