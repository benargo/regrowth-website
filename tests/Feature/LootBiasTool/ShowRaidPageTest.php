<?php

namespace Tests\Feature\LootBiasTool;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\Item;
use App\Models\LootPriority;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('loot')]
class ShowRaidPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();
    }

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
        $raid = Raid::factory()->create();

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Raids/Show')
            ->missing('selected_phase_id')
            ->missing('selected_raid_id')
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
            ->component('Loot/Raids/Show')
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
            ->component('Loot/Raids/Show')
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
        $priority = LootPriority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 100]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));
        $response->assertOk();

        $pageData = $response->viewData('page');
        $this->assertArrayNotHasKey('boss_items', $pageData['props']);

        $partialResponse = $this->actingAs($user)->get($this->raidUrl($raid)."?boss_id={$boss->id}", [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $pageData['version'],
            'X-Inertia-Partial-Component' => 'Loot/Raids/Show',
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
    public function it_includes_trash_boss_when_items_have_no_boss(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        Boss::factory()->create(['raid_id' => $raid->id, 'name' => 'Real Boss']);

        Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => null]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('bosses')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('bosses', 2)
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

        Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('bosses')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('bosses', 1)
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

        $response = $this->actingAs($user)->get($this->raidUrl($raid));
        $pageData = $response->viewData('page');

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

        Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => null]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));
        $pageData = $response->viewData('page');

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

        $item = Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => null]);
        Comment::factory()->count(3)->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
        ]);

        $response = $this->actingAs($user)->get($this->raidUrl($raid));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->missing('bosses')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->where('bosses.0.comments_count', 3)
            )
        );
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
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);
    }

    protected function raidUrl(Raid $raid, ?string $name = null): string
    {
        $name ??= Str::slug($raid->name);

        return "/loot/raids/{$raid->id}/{$name}";
    }
}
