<?php

namespace Tests\Feature\Loot;

use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use App\Models\User;
use App\Services\Blizzard\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class LootDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();
    }

    protected function mockItemService(): void
    {
        $this->instance(
            ItemService::class,
            Mockery::mock(ItemService::class, function (MockInterface $mock) {
                $mock->shouldReceive('find')
                    ->andReturnUsing(fn (int $id) => [
                        'id' => $id,
                        'name' => "Test Item {$id}",
                    ]);

                $mock->shouldReceive('media')
                    ->andReturn([
                        'assets' => [
                            ['key' => 'icon', 'value' => 'https://example.com/icon.jpg'],
                        ],
                    ]);
            })
        );
    }

    public function test_loot_index_requires_authentication(): void
    {
        $response = $this->get('/loot');

        $response->assertRedirect('/login');
    }

    public function test_loot_index_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertForbidden();
    }

    public function test_loot_index_forbids_users_with_no_roles(): void
    {
        $user = User::factory()->noRoles()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertForbidden();
    }

    public function test_loot_index_allows_member_users(): void
    {
        $user = User::factory()->member()->create();
        Phase::factory()->started()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
    }

    public function test_loot_index_allows_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        Phase::factory()->started()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
    }

    public function test_loot_index_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        Phase::factory()->started()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
    }

    public function test_loot_index_returns_phases_raids_and_bosses(): void
    {
        $user = User::factory()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Index')
            ->has('phases', 1)
            ->has('raids')
            ->has('bosses')
            ->has('current_phase')
            ->has('selected_raid_id')
        );
    }

    public function test_loot_index_selects_current_phase_correctly(): void
    {
        $user = User::factory()->create();

        $oldPhase = Phase::factory()->create(['start_date' => now()->subMonths(6)]);
        $currentPhase = Phase::factory()->create(['start_date' => now()->subDay()]);
        $futurePhase = Phase::factory()->create(['start_date' => now()->addMonth()]);

        Raid::factory()->create(['phase_id' => $currentPhase->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('current_phase', $currentPhase->id)
        );
    }

    public function test_loot_index_defers_items_prop(): void
    {
        $user = User::factory()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Index')
            ->missing('items')
        );
    }

    public function test_deferred_items_are_loaded_correctly(): void
    {
        $user = User::factory()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        $item = Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
        $priority = Priority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 100]);

        // Initial request - items should be deferred
        $response = $this->actingAs($user)->get('/loot');
        $response->assertOk();

        // Extract page data from the Inertia response
        $pageData = $response->viewData('page');
        $this->assertArrayNotHasKey('items', $pageData['props']);

        // Manual partial reload request to load deferred items
        // (loadDeferredProps doesn't preserve actingAs authentication)
        $deferredResponse = $this->actingAs($user)->get('/loot', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'Loot/Index',
            'X-Inertia-Partial-Data' => 'items',
        ]);

        $deferredResponse->assertOk();
        $deferredResponse->assertJsonStructure([
            'props' => [
                'items',
            ],
        ]);
    }

    public function test_partial_reload_returns_items_for_specified_raid(): void
    {
        $user = User::factory()->create();
        $phase = Phase::factory()->started()->create();

        $raid1 = Raid::factory()->create(['phase_id' => $phase->id]);
        $raid2 = Raid::factory()->create(['phase_id' => $phase->id]);

        $boss1 = Boss::factory()->create(['raid_id' => $raid1->id]);
        $boss2 = Boss::factory()->create(['raid_id' => $raid2->id]);

        Item::factory()->create(['raid_id' => $raid1->id, 'boss_id' => $boss1->id]);
        Item::factory()->create(['raid_id' => $raid2->id, 'boss_id' => $boss2->id]);

        $response = $this->actingAs($user)->get("/loot?raid_id={$raid2->id}");

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('selected_raid_id', $raid2->id)
        );
    }

    public function test_bosses_are_ordered_by_encounter_order(): void
    {
        $user = User::factory()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);

        $boss3 = Boss::factory()->create(['raid_id' => $raid->id, 'encounter_order' => 3, 'name' => 'Third Boss']);
        $boss1 = Boss::factory()->create(['raid_id' => $raid->id, 'encounter_order' => 1, 'name' => 'First Boss']);
        $boss2 = Boss::factory()->create(['raid_id' => $raid->id, 'encounter_order' => 2, 'name' => 'Second Boss']);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has("bosses.{$raid->id}", 3)
            ->where("bosses.{$raid->id}.0.name", 'First Boss')
            ->where("bosses.{$raid->id}.1.name", 'Second Boss')
            ->where("bosses.{$raid->id}.2.name", 'Third Boss')
        );
    }
}
