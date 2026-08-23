<?php

namespace Tests\Unit\Providers;

use App\Models\DiscordRole;
use App\Models\Permission;
use App\Models\Report;
use App\Models\User;
use App\Services\LootPriorities\HighestPriorityStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('platform')]
class AppServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    // ==================== whereNone macro ====================

    #[Test]
    public function it_registers_the_where_none_macro_on_the_eloquent_builder(): void
    {
        $this->assertTrue(method_exists(Report::query(), 'whereNone') || is_callable([Report::query(), 'whereNone']));
    }

    #[Test]
    public function where_none_macro_returns_no_rows(): void
    {
        Report::factory()->count(3)->create();

        $results = Report::query()->whereNone()->get();

        $this->assertCount(0, $results);
    }

    // ==================== container bindings ====================

    #[Test]
    public function it_registers_the_highest_priority_stats_service(): void
    {
        $this->assertTrue($this->app->bound(HighestPriorityStats::class));
        $this->assertInstanceOf(HighestPriorityStats::class, $this->app->make(HighestPriorityStats::class));
    }

    #[Test]
    public function it_registers_highest_priority_stats_as_a_singleton(): void
    {
        $first = $this->app->make(HighestPriorityStats::class);
        $second = $this->app->make(HighestPriorityStats::class);

        $this->assertSame($first, $second);
    }

    // ==================== authorization gates ====================

    #[Test]
    public function it_defines_the_view_priorities_page_gate(): void
    {
        $this->assertTrue(Gate::has('view-priorities-page'));
    }

    #[Test]
    public function it_grants_the_view_priorities_page_gate_to_authorized_users(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'view-priorities-page', 'guard_name' => 'web']);
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5, 'is_visible' => true]
        );
        $officerRole->givePermissionTo($permission);

        $user = User::factory()->officer()->create();

        $this->assertTrue(Gate::forUser($user)->allows('view-priorities-page'));
    }

    #[Test]
    public function it_denies_the_view_priorities_page_gate_to_unauthorized_users(): void
    {
        $user = User::factory()->member()->create();

        $this->assertFalse(Gate::forUser($user)->allows('view-priorities-page'));
    }
}
