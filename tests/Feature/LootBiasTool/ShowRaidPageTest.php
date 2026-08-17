<?php

namespace Tests\Feature\LootBiasTool;

use App\Models\Boss;
use App\Models\Comment;
use App\Models\Item;
use App\Models\LootPriority;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('loot')]
class ShowRaidPageTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();
    }

    // ==================== show — access control ====================

    #[Test]
    public function loot_raid_allows_unauthenticated_users(): void
    {
        $raid = Raid::factory()->create();

        $response = $this->get($this->raidUrl($raid));

        $response->assertOk();
    }

    #[Group('authorization')]
    #[Test]
    public function loot_raid_allows_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $raid = Raid::factory()->create();

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
    }

    #[Group('authorization')]
    #[Test]
    public function loot_raid_allows_users_with_no_roles(): void
    {
        $user = User::factory()->noRoles()->create();
        $raid = Raid::factory()->create();

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
    }

    #[Test]
    public function loot_raid_allows_member_users(): void
    {
        $user = User::factory()->member()->create();
        $raid = Raid::factory()->create();

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
    }

    // ==================== show — slug resolution ====================

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

    // ==================== show — rendering ====================

    #[Test]
    public function loot_raid_renders_raid_page_with_correct_props(): void
    {
        $user = User::factory()->member()->create();
        $raid = Raid::factory()->create();

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Raids/Show')
            ->missing('bosses')
            ->missing('selected_phase_id')
            ->missing('selected_raid_id')
        );
    }

    #[Test]
    public function loot_raid_includes_raid_prop(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Raids/Show')
            ->has('raid.data', fn (Assert $raidProp) => $raidProp
                ->where('id', $raid->id)
                ->where('name', $raid->name)
                ->where('slug', $raid->slug)
                ->etc()
            )
        );
    }

    #[Test]
    public function loot_raid_includes_bosses_with_comments_count_in_raid_prop(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        $item = Item::factory()->fromBoss($boss)->create();
        Comment::factory()->count(3)->create(['commentable_id' => (string) $item->id, 'commentable_type' => Item::class]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Raids/Show')
            ->has('raid.data.bosses.0', fn (Assert $bossProp) => $bossProp
                ->where('id', $boss->id)
                ->where('name', $boss->name)
                ->where('slug', $boss->slug)
                ->where('encounter_order', $boss->encounter_order)
                ->where('comments_count', 3)
                ->etc()
            )
        );
    }

    #[Test]
    public function loot_raid_includes_bosses_in_raid_prop(): void
    {
        $user = User::factory()->member()->create();
        $raid = Raid::factory()->create();
        Boss::factory()->create(['raid_id' => $raid->id]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Raids/Show')
            ->missing('bosses')
            ->has('raid.data.bosses')
        );
    }

    // ==================== boss items ====================

    #[Test]
    public function loot_raid_boss_items_not_included_on_initial_load(): void
    {
        $user = User::factory()->member()->create();
        $raid = Raid::factory()->create();
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        Item::factory()->fromBoss($boss)->create();

        $response = $this->actingAs($user)->get($this->raidUrl($raid));
        $pageData = $response->viewData('page');

        // boss_items is present as a keyed object; each boss key is null until a partial reload fetches it
        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Raids/Show')
            ->has('boss_items')
        );

        // Verify via a partial request that the key resolves to null when not specifically requested
        $partialResponse = $this->actingAs($user)->get($this->raidUrl($raid), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'Loot/Raids/Show',
            'X-Inertia-Partial-Data' => 'raid',
        ]);
        $partialResponse->assertOk();
        $partialResponse->assertJsonMissingPath('props.boss_items');
    }

    #[Test]
    public function boss_items_are_loaded_via_partial_reload(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);
        $item = Item::factory()->fromBoss($boss)->create();
        $priority = LootPriority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 100]);
        Comment::factory()->count(2)->create(['commentable_id' => (string) $item->id, 'commentable_type' => Item::class]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));
        $response->assertOk();

        $pageData = $response->viewData('page');
        $this->assertNull($pageData['props']['boss_items'][(string) $boss->id] ?? null);

        $partialResponse = $this->actingAs($user)->get($this->raidUrl($raid), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'Loot/Raids/Show',
            'X-Inertia-Partial-Data' => "boss_items.{$boss->id}",
        ]);

        $partialResponse->assertOk();
        $partialResponse->assertJsonPath("props.boss_items.{$boss->id}.data.0.id", $item->id);
        $partialResponse->assertJsonPath("props.boss_items.{$boss->id}.data.0.comments_count", 2);
        $partialResponse->assertJsonPath("props.boss_items.{$boss->id}.data.0.priorities.0.id", $priority->id);
    }

    // ==================== trash items ====================

    #[Test]
    public function it_flags_has_trash_items_when_items_have_no_boss(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        Boss::factory()->create(['raid_id' => $raid->id, 'name' => 'Real Boss']);

        Item::factory()->trashDrop()->inRaids([$raid])->create();

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('raid.data.has_trash_items', true)
        );
    }

    #[Test]
    public function it_does_not_flag_has_trash_items_when_no_items_without_boss(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id, 'name' => 'Real Boss']);

        Item::factory()->fromBoss($boss)->create();

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('raid.data.has_trash_items', false)
        );
    }

    #[Test]
    public function boss_items_returns_empty_collection_when_boss_has_no_items(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));
        $pageData = $response->viewData('page');

        $partialResponse = $this->actingAs($user)->get($this->raidUrl($raid), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'Loot/Raids/Show',
            'X-Inertia-Partial-Data' => "boss_items.{$boss->id}",
        ]);

        $partialResponse->assertOk();
        $partialResponse->assertJsonPath("props.boss_items.{$boss->id}.data", []);
    }

    #[Test]
    public function trash_items_are_loaded_via_partial_reload(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        Boss::factory()->create(['raid_id' => $raid->id]);

        $item = Item::factory()->trashDrop()->inRaids([$raid])->create();

        $response = $this->actingAs($user)->get($this->raidUrl($raid));
        $pageData = $response->viewData('page');

        $partialResponse = $this->actingAs($user)->get($this->raidUrl($raid), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'Loot/Raids/Show',
            'X-Inertia-Partial-Data' => 'trash_items',
        ]);

        $partialResponse->assertOk();
        $partialResponse->assertJsonStructure([
            'props' => [
                'trash_items' => ['data'],
            ],
        ]);
        $partialResponse->assertJsonPath('props.trash_items.data.0.id', $item->id);
    }

    #[Test]
    public function trash_comments_count_is_included_in_raid_prop(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);

        $item = Item::factory()->trashDrop()->inRaids([$raid])->create();
        Comment::factory()->count(3)->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
        ]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('raid.data.trash_comments_count', 3)
        );
    }

    // ==================== cross-raid trash items ====================

    #[Test]
    public function a_cross_raid_trash_item_is_listed_under_both_raids(): void
    {
        $hyjal = Raid::factory()->create(['name' => 'Hyjal Summit']);
        $blackTemple = Raid::factory()->create(['name' => 'Black Temple']);

        $item = Item::factory()
            ->trashDrop()
            ->withName('Boots of Effortless Sneaking')
            ->inRaids([$hyjal, $blackTemple])
            ->create();

        foreach ([$hyjal, $blackTemple] as $raid) {
            $this->get($this->raidUrl($raid).'?trash_items=1')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('raid.data.has_trash_items', true)
                );

            $this->assertTrue(
                $raid->trashItems()->whereKey($item->id)->exists(),
                "Item missing from {$raid->name} trash items",
            );
        }
    }

    // ==================== helpers ====================

    protected function raidUrl(Raid $raid, ?string $name = null): string
    {
        $name ??= Str::slug($raid->name);

        return "/loot/raids/{$raid->id}/{$name}";
    }
}
