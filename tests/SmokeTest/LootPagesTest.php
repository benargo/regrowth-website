<?php

namespace Tests\SmokeTest;

use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('loot')]
class LootPagesTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();

        $editItems = Permission::firstOrCreate(['name' => 'edit-items', 'guard_name' => 'web']);

        $officerRole = DiscordRole::factory()->officer()->create();
        $officerRole->givePermissionTo($editItems);
    }

    #[Test]
    public function loot_index_allows_unauthenticated_users(): void
    {
        $response = $this->get(route('loot.index'));

        $response->assertOk();
    }

    #[Group('happy-path')]
    #[Test]
    public function loot_index_loads(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Group('happy-path')]
    #[Test]
    public function loot_raid_page_loads(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get(route('loot.raids.show', ['raid' => $raid->id, 'name' => Str::slug($raid->name)]));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Group('happy-path')]
    #[Test]
    public function loot_comments_page_loads(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get('/loot/comments');

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Group('happy-path')]
    #[Test]
    public function loot_item_show_page_loads(): void
    {
        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', [
            'item' => $item->id,
            'slug' => $item->slug,
        ]));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    #[Group('happy-path')]
    #[Test]
    public function loot_item_edit_page_loads(): void
    {
        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.edit', [
            'item' => $item->id,
            'slug' => 'test-item-'.$item->id,
        ]));

        $response->assertOk();
        $response->assertSee('Regrowth');
    }

    private function createTestItem(): Item
    {
        $item = Item::factory()->fromBoss()->create();
        $item->update(['name' => "Test Item {$item->id}"]);

        return $item->fresh();
    }
}
