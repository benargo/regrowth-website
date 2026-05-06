<?php

namespace Tests\Feature\LootCouncil;

use App\Models\Boss;
use App\Models\DiscordRole;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BiasToolRaidTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();

        $viewLootBiasTool = Permission::firstOrCreate(['name' => 'view-loot-bias-tool', 'guard_name' => 'web']);
        DiscordRole::firstOrCreate(['id' => '829022020301094922'], ['name' => 'Member', 'position' => 2, 'is_visible' => true])->givePermissionTo($viewLootBiasTool);
        DiscordRole::firstOrCreate(['id' => '1265247017215594496'], ['name' => 'Raider', 'position' => 4, 'is_visible' => true])->givePermissionTo($viewLootBiasTool);
        DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 6, 'is_visible' => true])->givePermissionTo($viewLootBiasTool);
    }

    protected function mockItemService(): void
    {
        $this->instance(
            BlizzardService::class,
            Mockery::mock(BlizzardService::class, function (MockInterface $mock) {
                $mock->shouldReceive('findItem')
                    ->andReturnUsing(fn (int $id) => [
                        'id' => $id,
                        'name' => "Test Item {$id}",
                    ]);

                $mock->shouldReceive('findMedia')
                    ->andReturn([
                        'assets' => [
                            ['key' => 'icon', 'value' => 'https://example.com/icon.jpg', 'file_data_id' => 123],
                        ],
                    ]);
            })
        );

        $this->instance(
            MediaService::class,
            Mockery::mock(MediaService::class, function (MockInterface $mock) {
                $mock->shouldReceive('get')
                    ->andReturn([123 => 'https://example.com/icon.jpg']);
            })
        );
    }

    protected function raidUrl(Raid $raid, ?string $name = null): string
    {
        $name ??= Str::slug($raid->name);

        return "/loot/raids/{$raid->id}/{$name}";
    }

    #[Test]
    public function loot_raid_requires_authentication(): void
    {
        $raid = Raid::factory()->create();

        $response = $this->get($this->raidUrl($raid));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function loot_raid_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $raid = Raid::factory()->create();

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertForbidden();
    }

    #[Test]
    public function loot_raid_forbids_users_with_no_roles(): void
    {
        $user = User::factory()->noRoles()->create();
        $raid = Raid::factory()->create();

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertForbidden();
    }

    #[Test]
    public function loot_raid_allows_member_users(): void
    {
        $user = User::factory()->member()->create();
        $raid = Raid::factory()->create();

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
    }

    #[Test]
    public function loot_raid_redirects_when_name_is_missing(): void
    {
        $user = User::factory()->member()->create();
        $raid = Raid::factory()->create();

        $response = $this->actingAs($user)->get("/loot/raids/{$raid->id}");

        $response->assertRedirect($this->raidUrl($raid));
        $response->assertStatus(303);
    }

    #[Test]
    public function loot_raid_redirects_when_name_is_wrong(): void
    {
        $user = User::factory()->member()->create();
        $raid = Raid::factory()->create();

        $response = $this->actingAs($user)->get($this->raidUrl($raid, 'wrong-name'));

        $response->assertRedirect($this->raidUrl($raid));
    }

    #[Test]
    public function loot_raid_renders_raid_page_with_correct_props(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('LootBiasTool/Raid')
            ->has('phases')
            ->where('selected_phase_id', $phase->id)
            ->where('selected_raid_id', $raid->id)
        );
    }

    #[Test]
    public function loot_raid_defers_bosses(): void
    {
        $user = User::factory()->member()->create();
        $raid = Raid::factory()->create();
        Boss::factory()->create(['raid_id' => $raid->id]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('LootBiasTool/Raid')
            ->missing('bosses')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('bosses')
            )
        );
    }

    #[Test]
    public function loot_raid_bosses_are_ordered_by_encounter_order(): void
    {
        $user = User::factory()->member()->create();
        $raid = Raid::factory()->create();

        Boss::factory()->create(['raid_id' => $raid->id, 'encounter_order' => 3, 'name' => 'Third Boss']);
        Boss::factory()->create(['raid_id' => $raid->id, 'encounter_order' => 1, 'name' => 'First Boss']);
        Boss::factory()->create(['raid_id' => $raid->id, 'encounter_order' => 2, 'name' => 'Second Boss']);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('bosses')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('bosses', 3)
                ->where('bosses.0.name', 'First Boss')
                ->where('bosses.1.name', 'Second Boss')
                ->where('bosses.2.name', 'Third Boss')
            )
        );
    }

    #[Test]
    public function loot_raid_boss_items_not_included_on_initial_load(): void
    {
        $user = User::factory()->member()->create();
        $raid = Raid::factory()->create();
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('LootBiasTool/Raid')
            ->missing('boss_items')
        );
    }

    #[Test]
    public function boss_items_are_loaded_via_partial_reload(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        $item = Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
        $priority = Priority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 100]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));
        $response->assertOk();

        $pageData = $response->viewData('page');
        $this->assertArrayNotHasKey('boss_items', $pageData['props']);

        $partialResponse = $this->actingAs($user)->get($this->raidUrl($raid)."?boss_id={$boss->id}", [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'LootBiasTool/Raid',
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
                    ],
                ],
            ],
        ]);
        $partialResponse->assertJsonPath('props.boss_items.data.bossId', $boss->id);
    }

    #[Test]
    public function loot_raid_stores_last_visited_raid_in_session(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertSessionHas("loot.last_visited_raid.{$phase->id}", $raid->id);
    }
}
