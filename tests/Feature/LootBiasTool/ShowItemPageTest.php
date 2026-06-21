<?php

namespace Tests\Feature\LootBiasTool;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Models\Boss;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();
    }

    #[Test]
    public function show_item_allows_unauthenticated_users(): void
    {
        $item = $this->createTestItem();

        $response = $this->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertOk();
    }

    #[Group('authorization')]
    #[Test]
    public function show_item_allows_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertOk();
    }

    #[Test]
    public function show_item_allows_member_users(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertOk();
    }

    #[Test]
    public function show_item_allows_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertOk();
    }

    #[Test]
    public function show_item_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertOk();
    }

    #[Test]
    public function show_item_redirects_from_null_slug_to_correct_slug(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id]));

        $response->assertRedirect(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));
        $response->assertStatus(303);
    }

    #[Test]
    public function show_item_redirects_from_incorrect_slug_to_correct_slug(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'wrong-slug']));

        $response->assertRedirect(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));
        $response->assertStatus(303);
    }

    #[Test]
    public function show_item_renders_with_correct_slug(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'test-item-'.$item->id]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Items/Show')
            ->has('item.data')
        );
    }

    #[Test]
    public function show_item_uses_fallback_slug_when_api_returns_not_found(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetItemRequest::class => MockResponse::make(
                body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'],
                status: 404,
            ),
        ]);

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id]));

        $response->assertRedirect(route('loot.items.show', ['item' => $item->id, 'name' => 'item-'.$item->id]));
        $response->assertStatus(303);
    }

    #[Test]
    public function show_item_renders_with_fallback_slug_when_api_returns_not_found(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetItemRequest::class => MockResponse::make(
                body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'],
                status: 404,
            ),
        ]);

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'name' => 'item-'.$item->id]));

        $response->assertOk();
    }

    protected function createTestItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        return Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
    }
}
