<?php

namespace Tests\Feature\LootBiasTool;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Models\Boss;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\LootPriority;
use App\Models\Permission;
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
class EditItemPageTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPermissions();
        $this->mockItemService();
    }

    #[Test]
    public function edit_item_requires_authentication(): void
    {
        $item = $this->createTestItem();

        $response = $this->get($this->editUrl($item));

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function edit_item_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function edit_item_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function edit_item_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertForbidden();
    }

    #[Test]
    public function edit_item_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertOk();
    }

    #[Test]
    public function edit_item_redirects_from_incorrect_slug_to_correct_slug(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item, 'wrong-slug'));

        $response->assertRedirect(route('loot.items.edit', ['item' => $item->id, 'slug' => 'test-item-'.$item->id]));
        $response->assertStatus(303);
    }

    #[Test]
    public function edit_item_renders_with_correct_slug(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Items/Edit')
            ->has('item.data')
        );
    }

    #[Test]
    public function edit_item_returns_item_and_all_priorities(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();
        $priority1 = LootPriority::factory()->role()->create(['title' => 'Tank']);
        $priority2 = LootPriority::factory()->role()->create(['title' => 'Healer']);
        $priority3 = LootPriority::factory()->role()->create(['title' => 'DPS']);

        $item->priorities()->attach($priority1->id, ['weight' => 0]);
        $item->priorities()->attach($priority2->id, ['weight' => 1]);

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Items/Edit')
            ->has('item.data')
            ->has('item.data.priorities', 2)
            ->has('priorities.data', 3)
            ->missing('allPriorities')
        );
    }

    #[Test]
    public function edit_item_renders_with_priorities_prop(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Items/Edit')
            ->has('priorities.data')
            ->has('item.data.raids')
            ->has('comments.data')
            ->missing('allPriorities')
            ->missing('raids')
        );
    }

    #[Test]
    public function edit_item_renders_using_db_data_when_blizzard_api_returns_not_found(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make($this->makeTokenResponse()),
            GetItemRequest::class => MockResponse::make(
                body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'],
                status: 404,
            ),
        ]);

        $response = $this->actingAs($user)->get($this->editUrl($item));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Items/Edit')
            ->where('item.data.name', $item->name)
        );
    }

    protected function setUpPermissions(): void
    {
        $editItems = Permission::firstOrCreate(['name' => 'edit-items', 'guard_name' => 'web']);

        $officerRole = DiscordRole::firstOrCreate(['id' => '829021769448816691'], ['name' => 'Officer', 'position' => 5, 'is_visible' => true]);
        $officerRole->givePermissionTo($editItems);
    }

    protected function createTestItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        $item = Item::factory()->fromBoss($boss)->create();
        $item->update(['name' => "Test Item {$item->id}"]);

        return $item->fresh();
    }

    /**
     * Generate the edit URL with the name in the correct path position.
     * The route helper puts optional parameters in query string, but we need it in the path.
     */
    protected function editUrl(Item $item, ?string $name = null): string
    {
        $slug = $name ?? 'test-item-'.$item->id;

        return "/loot/items/{$item->id}/{$slug}/edit";
    }
}
