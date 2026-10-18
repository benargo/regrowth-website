<?php

namespace Tests\Feature\LootBiasTool;

use App\Enums\LootPriorityType;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\LootPriority;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('loot')]
class HighestPriorityStatsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Cache::tags(['lootcouncil'])->flush();

        $permission = Permission::firstOrCreate(['name' => 'view-priorities-page', 'guard_name' => 'web']);
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5, 'is_visible' => true]
        );
        $officerRole->givePermissionTo($permission);
    }

    protected function itemInPhase(Phase $phase): Item
    {
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $item = Item::factory()->create();
        $item->raids()->attach($raid->id);

        return $item;
    }

    // ==================== access control ====================

    #[Group('authorization')]
    #[Test]
    public function priorities_requires_authentication(): void
    {
        $response = $this->get(route('loot.priorities'));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function priorities_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('loot.priorities'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function priorities_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('loot.priorities'));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function priorities_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('loot.priorities'));

        $response->assertOk();
    }

    // ==================== rendering ====================

    #[Test]
    public function priorities_renders_the_inertia_page(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('loot.priorities'));

        $response->assertInertia(fn (Assert $page) => $page->component('Loot/Priorities'));
    }

    #[Test]
    public function priorities_defers_the_table_prop(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('loot.priorities'));

        $response->assertInertia(fn (Assert $page) => $page->missing('table'));
    }

    #[Test]
    public function priorities_table_prop_loads_via_deferred_props(): void
    {
        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $priority = LootPriority::factory()->role()->create(['title' => 'Tank']);
        $item->priorities()->attach($priority->id, ['weight' => 0]);

        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('loot.priorities'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('table.role_rows', 1)
                ->where('table.role_rows.0.title', 'Tank')
            )
        );
    }

    #[Test]
    public function priorities_table_prop_loads_from_a_cache_store_that_disallows_object_unserialization(): void
    {
        config([
            'cache.stores.array.serialize' => true,
            'cache.serializable_classes' => false,
        ]);
        Cache::purge('array');

        $phase = Phase::factory()->create(['number' => 1]);
        $item = $this->itemInPhase($phase);
        $priority = LootPriority::factory()->spec()->withPlayableClass()->create(['title' => 'Feral']);
        $item->priorities()->attach($priority->id, ['weight' => 0]);

        $user = User::factory()->officer()->create();

        foreach ([1, 2] as $request) {
            $this->actingAs($user)->get(route('loot.priorities'))
                ->assertInertia(fn (Assert $page) => $page
                    ->loadDeferredProps(fn (Assert $reload) => $reload
                        ->has('table.class_groups', 1)
                        ->missing('table.class_groups.0.__PHP_Incomplete_Class_Name')
                        ->where('table.class_groups.0.kind', 'class')
                        ->has('table.class_groups.0.children', 1)
                        ->where('table.class_groups.0.children.0.title', 'Feral')
                        ->where('table.class_groups.0.children.0.type', LootPriorityType::SPEC->value)
                    )
                );
        }
    }

    #[Test]
    public function priorities_renders_an_empty_table_when_no_priorities_have_items(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('loot.priorities'));

        $response->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('table.role_rows', 0)
                ->has('table.class_groups', 0)
            )
        );
    }
}
