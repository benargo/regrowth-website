<?php

namespace Tests\Unit\Policies;

use App\Models\DiscordRole;
use App\Models\Event;
use App\Models\User;
use App\Policies\EventPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EventPolicyTest extends TestCase
{
    use RefreshDatabase;

    private EventPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new EventPolicy;
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
    public function it_allows_view_with_view_raid_plans_permission(): void
    {
        $user = $this->userWithPermission('view-raid-plans');
        $event = Event::factory()->create();

        $this->assertTrue($this->policy->view($user, $event));
    }

    #[Test]
    public function it_denies_view_without_permission(): void
    {
        $user = $this->userWithoutPermission();
        $event = Event::factory()->create();

        $this->assertFalse($this->policy->view($user, $event));
    }

    #[Test]
    public function it_denies_view_with_unrelated_permission(): void
    {
        $user = $this->userWithPermission('manage-reports');
        $event = Event::factory()->create();

        $this->assertFalse($this->policy->view($user, $event));
    }
}
