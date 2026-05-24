<?php

namespace Tests\Feature\Loot;

use App\Models\DiscordRole;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
use App\Models\Permission;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ItemSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $viewLootBiasTool = Permission::firstOrCreate(['name' => 'view-loot-bias-tool', 'guard_name' => 'web']);
        DiscordRole::firstOrCreate(['id' => '829022020301094922'], ['name' => 'Member', 'position' => 2, 'is_visible' => true])->givePermissionTo($viewLootBiasTool);
    }

    #[Test]
    public function search_requires_authentication(): void
    {
        $this->getJson(route('loot.items.search', ['query' => 'sword']))
            ->assertUnauthorized();
    }

    #[Test]
    public function search_forbids_unauthorized_users(): void
    {
        $user = User::factory()->guest()->create();

        $this->actingAs($user)
            ->getJson(route('loot.items.search', ['query' => 'sword']))
            ->assertForbidden();
    }

    #[Test]
    public function search_returns_matching_items(): void
    {
        $user = User::factory()->member()->create();
        $matching = Item::factory()->withName('Blade of the Betrayer')->create();
        Item::factory()->withName('Fist of the Fallen')->create();

        $this->actingAs($user)
            ->getJson(route('loot.items.search', ['query' => 'Blade']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['id' => $matching->id, 'name' => 'Blade of the Betrayer']);
    }

    #[Test]
    public function search_returns_empty_array_for_no_matches(): void
    {
        $user = User::factory()->member()->create();
        Item::factory()->withName('Blade of the Betrayer')->create();

        $this->actingAs($user)
            ->getJson(route('loot.items.search', ['query' => 'xyz_nomatch']))
            ->assertOk()
            ->assertExactJson([]);
    }

    #[Test]
    public function search_returns_empty_array_for_blank_query(): void
    {
        $user = User::factory()->member()->create();
        Item::factory()->withName('Blade of the Betrayer')->create();

        $this->actingAs($user)
            ->getJson(route('loot.items.search', ['query' => '   ']))
            ->assertOk()
            ->assertExactJson([]);
    }

    #[Test]
    public function search_returns_priorities_count(): void
    {
        $user = User::factory()->member()->create();
        $item = Item::factory()->withName('Sword of Darkness')->create();
        $priorities = Priority::factory()->count(2)->create();
        $item->priorities()->attach($priorities->mapWithKeys(fn ($p) => [$p->id => ['weight' => 1]]));

        $this->actingAs($user)
            ->getJson(route('loot.items.search', ['query' => 'Sword']))
            ->assertOk()
            ->assertJsonFragment(['priorities_count' => 2]);
    }

    #[Test]
    public function search_returns_comments_count(): void
    {
        $user = User::factory()->member()->create();
        $item = Item::factory()->withName('Sword of Darkness')->create();
        Comment::factory()->count(3)->create(['item_id' => $item->id]);

        $this->actingAs($user)
            ->getJson(route('loot.items.search', ['query' => 'Sword']))
            ->assertOk()
            ->assertJsonFragment(['comments_count' => 3]);
    }

    #[Test]
    public function search_returns_at_most_ten_results(): void
    {
        $user = User::factory()->member()->create();
        $raid = Raid::factory()->create();
        Item::factory()->count(15)->withName('Common Sword')->create(['raid_id' => $raid->id]);

        $this->actingAs($user)
            ->getJson(route('loot.items.search', ['query' => 'Common Sword']))
            ->assertOk()
            ->assertJsonCount(10);
    }

    #[Test]
    public function search_response_contains_expected_fields(): void
    {
        $user = User::factory()->member()->create();
        Item::factory()->withName('The Corrupted Blade')->withIcon()->create();

        $this->actingAs($user)
            ->getJson(route('loot.items.search', ['query' => 'Corrupted']))
            ->assertOk()
            ->assertJsonStructure([['id', 'name', 'slug', 'icon', 'priorities_count', 'comments_count']]);
    }
}
