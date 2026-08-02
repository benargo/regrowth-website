<?php

namespace Tests\Feature\LootBiasTool;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('loot')]
class ShowItemPageTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    #[Test]
    public function show_item_allows_unauthenticated_users(): void
    {
        $item = $this->createTestItem();

        $response = $this->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
    }

    #[Group('authorization')]
    #[Test]
    public function show_item_allows_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
    }

    #[Test]
    public function show_item_allows_member_users(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
    }

    #[Test]
    public function show_item_allows_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
    }

    #[Test]
    public function show_item_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
    }

    #[Test]
    public function show_item_redirects_from_null_slug_to_correct_slug(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id]));

        $response->assertRedirect(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));
    }

    #[Test]
    public function show_item_redirects_from_incorrect_slug_to_correct_slug(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => 'wrong-slug']));

        $response->assertRedirect(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));
    }

    #[Test]
    public function show_item_renders_with_correct_slug(): void
    {
        $this->mockItemService();

        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Items/Show')
            ->has('item.data', fn (Assert $prop) => $prop
                ->where('id', $item->id)
                ->has('raid')
                ->has('boss')
                ->has('comments.data')
                ->has('comments.links')
                ->has('comments.meta')
                ->has('inventory_type')
                ->has('item_class')
                ->has('item_subclass')
                ->etc()
            )
            ->missing('raid')
            ->missing('boss')
            ->missing('comments')
        );
    }

    #[Test]
    public function show_item_redirects_to_fallback_slug_when_item_has_no_name(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItemWithoutName();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id]));

        $response->assertRedirect(route('loot.items.show', ['item' => $item->id, 'slug' => "item-{$item->id}"]));
    }

    #[Test]
    public function show_item_renders_with_fallback_slug_when_item_has_no_name(): void
    {
        $this->mockItemService();

        $user = User::factory()->member()->create();
        $item = $this->createTestItemWithoutName();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => "item-{$item->id}"]));

        $response->assertOk();
    }

    #[Test]
    public function show_item_renders_using_db_data_when_blizzard_api_returns_not_found(): void
    {
        $this->mockItemService();

        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetItemRequest::class => MockResponse::make(
                body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'],
                status: 404,
            ),
        ]);

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Items/Show')
            ->where('item.data.name', $item->name)
        );
    }

    #[Test]
    public function show_item_renders_with_null_boss_when_item_has_no_boss(): void
    {
        $this->mockItemService();

        $user = User::factory()->member()->create();
        $item = $this->createTestItemWithoutBoss();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Items/Show')
            ->where('item.data.boss', null)
            ->has('item.data.raid')
        );
    }

    protected function createTestItem(): Item
    {
        return Item::factory()->fromBoss()->withName('Test Item')->create();
    }

    protected function createTestItemWithoutBoss(): Item
    {
        return Item::factory()->withRaid()->trashDrop()->withName('Test Item')->create();
    }

    protected function createTestItemWithoutName(): Item
    {
        return Item::factory()->fromBoss()->create();
    }
}
