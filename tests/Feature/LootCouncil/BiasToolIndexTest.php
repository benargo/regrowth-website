<?php

namespace Tests\Feature\LootCouncil;

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

class BiasToolIndexTest extends TestCase
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
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('LootBiasTool/Index')
            ->has('phases', 1)
            ->has('raids')
            ->missing('bosses') // Bosses are deferred
            ->has('current_phase')
            ->has('selected_raid_id')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('bosses')
            )
        );
    }

    public function test_loot_index_selects_current_phase_correctly(): void
    {
        $user = User::factory()->member()->create();

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

    public function test_loot_index_has_optional_boss_items_prop(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('LootBiasTool/Index')
            ->missing('boss_items') // Optional prop is not included on initial load
        );
    }

    public function test_boss_items_are_loaded_via_partial_reload(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        $item = Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
        $priority = Priority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 100]);

        // Initial request - boss_items should not be included (optional prop)
        $response = $this->actingAs($user)->get('/loot');
        $response->assertOk();

        // Extract page data from the Inertia response
        $pageData = $response->viewData('page');
        $this->assertArrayNotHasKey('boss_items', $pageData['props']);

        // Partial reload request to load boss items for a specific boss
        $partialResponse = $this->actingAs($user)->get("/loot?boss_id={$boss->id}", [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'LootBiasTool/Index',
            'X-Inertia-Partial-Data' => 'boss_items',
        ]);

        $partialResponse->assertOk();
        $partialResponse->assertJsonStructure([
            'props' => [
                'boss_items' => [
                    'data' => [
                        'bossId',
                        'items',
                        'commentsCount',
                    ]
                ],
            ],
        ]);
        $partialResponse->assertJsonPath('props.boss_items.data.bossId', $boss->id);
    }

    public function test_partial_reload_returns_items_for_specified_raid(): void
    {
        $user = User::factory()->member()->create();
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
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);

        $boss3 = Boss::factory()->create(['raid_id' => $raid->id, 'encounter_order' => 3, 'name' => 'Third Boss']);
        $boss1 = Boss::factory()->create(['raid_id' => $raid->id, 'encounter_order' => 1, 'name' => 'First Boss']);
        $boss2 = Boss::factory()->create(['raid_id' => $raid->id, 'encounter_order' => 2, 'name' => 'Second Boss']);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('bosses') // Bosses are deferred
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has("bosses.{$raid->id}", 3)
                ->where("bosses.{$raid->id}.0.name", 'First Boss')
                ->where("bosses.{$raid->id}.1.name", 'Second Boss')
                ->where("bosses.{$raid->id}.2.name", 'Third Boss')
            )
        );
    }

    public function test_partial_reload_returns_bosses_for_new_raid(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();

        $raid1 = Raid::factory()->create(['phase_id' => $phase->id]);
        $raid2 = Raid::factory()->create(['phase_id' => $phase->id]);

        Boss::factory()->create(['raid_id' => $raid1->id, 'name' => 'Raid 1 Boss']);
        Boss::factory()->create(['raid_id' => $raid2->id, 'name' => 'Raid 2 Boss']);

        // Initial request loads raid1 by default
        $response = $this->actingAs($user)->get('/loot');
        $response->assertOk();

        // Extract page data from the Inertia response
        $pageData = $response->viewData('page');

        // Partial reload request to load bosses for raid2
        $partialResponse = $this->actingAs($user)->get("/loot?raid_id={$raid2->id}", [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'LootBiasTool/Index',
            'X-Inertia-Partial-Data' => 'selected_raid_id,bosses',
        ]);

        $partialResponse->assertOk();
        $partialResponse->assertJsonPath('props.selected_raid_id', $raid2->id);
        $partialResponse->assertJsonPath("props.bosses.{$raid2->id}.0.name", 'Raid 2 Boss');
    }
}
