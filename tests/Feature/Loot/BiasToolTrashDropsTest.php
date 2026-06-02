<?php

namespace Tests\Feature\Loot;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Models\Boss;
use App\Models\DiscordRole;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use App\Models\Permission;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class BiasToolTrashDropsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();

        $viewLootBiasTool = Permission::firstOrCreate(['name' => 'view-loot-bias-tool', 'guard_name' => 'web']);
        DiscordRole::firstOrCreate(['id' => '829022020301094922'], ['name' => 'Member', 'position' => 2, 'is_visible' => true])->givePermissionTo($viewLootBiasTool);
    }

    protected function mockItemService(): void
    {
        Storage::fake('public');

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetItemRequest::class => function (PendingRequest $pendingRequest): MockResponse {
                $path = parse_url($pendingRequest->getUrl(), PHP_URL_PATH) ?: '';
                $segments = explode('/', trim($path, '/'));
                $itemId = (int) ($segments[array_key_last($segments)] ?? 0);

                return MockResponse::make(body: [
                    'id' => $itemId,
                    'name' => "Test Item {$itemId}",
                ], status: 200);
            },
            GetItemMediaRequest::class => MockResponse::make(body: ['id' => 0, 'assets' => []], status: 200),
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);
    }

    protected function raidUrl(Raid $raid, ?string $name = null): string
    {
        $name ??= Str::slug($raid->name);

        return "/loot/raids/{$raid->id}/{$name}";
    }

    #[Test]
    public function it_includes_trash_boss_when_items_have_no_boss(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        Boss::factory()->create(['raid_id' => $raid->id, 'name' => 'Real Boss']);

        // Create an item with no boss_id (trash drop)
        Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => null]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('bosses')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('bosses', 2) // Real boss + Trash drops
                ->where('bosses.1.name', 'Trash drops')
                ->where('bosses.1.id', -1 * $raid->id)
            )
        );
    }

    #[Test]
    public function it_does_not_include_trash_boss_when_no_items_without_boss(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id, 'name' => 'Real Boss']);

        // Only items with a boss
        Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('bosses')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('bosses', 1) // Only the real boss
            )
        );
    }

    #[Test]
    public function it_returns_empty_resource_for_null_boss_id(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        Boss::factory()->create(['raid_id' => $raid->id]);

        // Initial request to get page version
        $response = $this->actingAs($user)->get($this->raidUrl($raid));
        $pageData = $response->viewData('page');

        // Partial reload without boss_id (defaults to null/0)
        $partialResponse = $this->actingAs($user)->get($this->raidUrl($raid), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'Loot/Raids/Show',
            'X-Inertia-Partial-Data' => 'boss_items',
        ]);

        $partialResponse->assertOk();
        $partialResponse->assertJsonPath('props.boss_items.data.bossId', null);
    }

    #[Test]
    public function it_returns_trash_items_for_negative_boss_id(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        Boss::factory()->create(['raid_id' => $raid->id]);

        // Create a trash item (no boss)
        Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => null]);

        // Initial request to get page version
        $response = $this->actingAs($user)->get($this->raidUrl($raid));
        $pageData = $response->viewData('page');

        // Partial reload with negative boss_id (trash drops)
        $negativeBossId = -1 * $raid->id;
        $partialResponse = $this->actingAs($user)->get($this->raidUrl($raid)."?boss_id={$negativeBossId}", [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'Loot/Raids/Show',
            'X-Inertia-Partial-Data' => 'boss_items',
        ]);

        $partialResponse->assertOk();
        $partialResponse->assertJsonPath('props.boss_items.data.bossId', $negativeBossId);
    }

    #[Test]
    public function trash_boss_includes_comment_count(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);

        // Create a trash item with comments
        $item = Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => null]);
        Comment::factory()->count(3)->create(['item_id' => $item->id]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('bosses')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('bosses.0.comments_count', 3)
            )
        );
    }
}
