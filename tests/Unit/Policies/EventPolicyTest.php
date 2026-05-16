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
    public function it_allows_view_for_recent_event_without_permission(): void
    {
        $user = $this->userWithoutPermission();
        $event = Event::factory()->create(['end_time' => now()->subHour()]);

        $this->assertTrue($this->policy->view($user, $event));
    }

    #[Test]
    public function it_allows_view_for_recent_event_at_two_hour_boundary(): void
    {
        $user = $this->userWithoutPermission();
        $event = Event::factory()->create(['end_time' => now()->subHours(2)->addSecond()]);

        $this->assertTrue($this->policy->view($user, $event));
    }

    #[Test]
    public function it_denies_view_for_old_event_without_permission(): void
    {
        $user = $this->userWithoutPermission();
        $event = Event::factory()->create(['end_time' => now()->subHours(3)]);

        $this->assertFalse($this->policy->view($user, $event));
    }

    #[Test]
    public function it_allows_view_for_old_event_with_view_old_raid_plans_permission(): void
    {
        $user = $this->userWithPermission('view-old-raid-plans');
        $event = Event::factory()->create(['end_time' => now()->subHours(3)]);

        $this->assertTrue($this->policy->view($user, $event));
    }

    #[Test]
    public function it_denies_view_for_old_event_with_unrelated_permission(): void
    {
        $user = $this->userWithPermission('manage-reports');
        $event = Event::factory()->create(['end_time' => now()->subHours(3)]);

        $this->assertFalse($this->policy->view($user, $event));
    }

    #[Test]
    public function it_allows_view_for_guest_on_recent_event(): void
    {
        $event = Event::factory()->create(['end_time' => now()->subHour()]);

        $this->assertTrue($this->policy->view(null, $event));
    }

    #[Test]
    public function it_denies_view_for_guest_on_old_event(): void
    {
        $event = Event::factory()->create(['end_time' => now()->subHours(3)]);

        $this->assertFalse($this->policy->view(null, $event));
    }

    #[Test]
    public function it_allows_update_with_manage_raid_plans_permission(): void
    {
        $user = $this->userWithPermission('manage-raid-plans');
        $event = Event::factory()->create();

        $this->assertTrue($this->policy->update($user, $event));
    }

    #[Test]
    public function it_denies_update_without_permission(): void
    {
        $user = $this->userWithoutPermission();
        $event = Event::factory()->create();

        $this->assertFalse($this->policy->update($user, $event));
    }

    #[Test]
    public function it_denies_update_with_unrelated_permission(): void
    {
        $user = $this->userWithPermission('view-raid-plans');
        $event = Event::factory()->create();

        $this->assertFalse($this->policy->update($user, $event));
    }
}
