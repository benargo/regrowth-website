<?php

namespace Tests\Feature\LootBiasTool;

use App\Contracts\Http\Middleware\SharesOriginRaidSession;
use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\Permission;
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

        $commentOnLootItems = Permission::firstOrCreate(['name' => 'comment-on-loot-items', 'guard_name' => 'web']);
        $markCommentAsResolved = Permission::firstOrCreate(['name' => 'mark-comment-as-resolved', 'guard_name' => 'web']);

        $raiderRole = DiscordRole::factory()->raider()->create();
        $raiderRole->givePermissionTo($commentOnLootItems);

        $officerRole = DiscordRole::factory()->officer()->create();
        $officerRole->givePermissionTo($commentOnLootItems);
        $officerRole->givePermissionTo($markCommentAsResolved);
    }

    // ==================== show — access control ====================

    #[Test]
    public function show_item_allows_unauthenticated_users(): void
    {
        $this->mockItemService();

        $item = $this->createTestItem();

        $response = $this->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
    }

    #[Group('authorization')]
    #[Test]
    public function show_item_allows_guest_users(): void
    {
        $this->mockItemService();

        $user = User::factory()->guest()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
    }

    #[Test]
    public function show_item_allows_member_users(): void
    {
        $this->mockItemService();

        $user = User::factory()->member()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
    }

    #[Test]
    public function show_item_allows_raider_users(): void
    {
        $this->mockItemService();

        $user = User::factory()->raider()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
    }

    #[Test]
    public function show_item_allows_officer_users(): void
    {
        $this->mockItemService();

        $user = User::factory()->officer()->create();
        $item = $this->createTestItem();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
    }

    // ==================== show — slug resolution ====================

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

    // ==================== show — rendering ====================

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
                ->has('raids')
                ->has('boss')
                ->has('inventory_type')
                ->has('item_class')
                ->has('item_subclass')
                ->etc()
            )
            ->has('comments.data')
            ->has('comments.links')
            ->has('comments.meta')
            ->missing('raids')
            ->missing('boss')
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
            ->has('item.data.raids')
        );
    }

    // ==================== show — comments ====================

    #[Group('comments')]
    #[Test]
    public function show_item_eager_loads_reaction_users_for_comments(): void
    {
        $item = $this->createTestItem();
        $author = User::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $author->id,
        ]);
        CommentReaction::factory()->forComment($comment)->byUser(User::factory()->create())->create();
        CommentReaction::factory()->forComment($comment)->byUser(User::factory()->create())->create();

        $response = $this->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('comments.data.0.reactions', 2)
            ->has('comments.data.0.reactions.0.user')
            ->has('comments.data.0.reactions.1.user')
        );
    }

    #[Group('comments')]
    #[Test]
    public function item_show_page_includes_comments(): void
    {
        $this->mockItemService();

        $item = $this->createTestItem();
        $user = User::factory()->member()->create();
        $commentAuthor = User::factory()->raider()->create();
        Comment::factory()->count(3)->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Items/Show')
            ->has('comments.data', 3)
        );
    }

    #[Group('comments')]
    #[Test]
    public function item_show_page_includes_can_create_comment_for_raiders(): void
    {
        $this->mockItemService();

        $item = $this->createTestItem();
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn ($perms) => collect($perms)->contains('comment-on-loot-items'))
        );
    }

    #[Group('comments')]
    #[Test]
    public function item_show_page_includes_can_create_comment_false_for_members(): void
    {
        $this->mockItemService();

        $item = $this->createTestItem();
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions', fn ($perms) => ! collect($perms)->contains('comment-on-loot-items'))
        );
    }

    #[Group('comments')]
    #[Test]
    public function comments_are_paginated(): void
    {
        $this->mockItemService();

        $item = $this->createTestItem();
        $user = User::factory()->member()->create();
        $commentAuthor = User::factory()->raider()->create();
        Comment::factory()->count(15)->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('comments.data', 10) // 10 per page
            ->has('comments.meta.links', 4)
            ->where('comments.meta.last_page', 2)
            ->where('comments.meta.total', 15)
            ->where('comments.meta.per_page', 10)
        );

        $secondPageResponse = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]).'?page=2');

        $secondPageResponse->assertOk();
        $secondPageResponse->assertInertia(fn (Assert $page) => $page
            ->has('comments.data', 5)
            ->where('comments.meta.current_page', 2)
        );
    }

    #[Group('comments')]
    #[Test]
    public function comments_are_ordered_by_latest(): void
    {
        $this->mockItemService();

        $item = $this->createTestItem();
        $user = User::factory()->member()->create();
        $commentAuthor = User::factory()->raider()->create();

        $oldComment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
            'body' => 'Old comment',
            'created_at' => now()->subDays(5),
        ]);

        $newComment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
            'body' => 'New comment',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('comments.data.0.id', $newComment->id)
            ->where('comments.data.1.id', $oldComment->id)
        );
    }

    #[Group('comments')]
    #[Test]
    public function comment_resource_includes_authorization_flags(): void
    {
        $this->mockItemService();

        $item = $this->createTestItem();
        $user = User::factory()->raider()->create();
        Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('comments.data.0.permissions.edit')
            ->has('comments.data.0.permissions.delete')
        );
    }

    #[Group('comments')]
    #[Test]
    public function comment_resource_includes_is_resolved(): void
    {
        $this->mockItemService();

        $item = $this->createTestItem();
        $user = User::factory()->member()->create();
        $commentAuthor = User::factory()->raider()->create();

        Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
            'is_resolved' => false,
        ]);

        Comment::factory()->resolved()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($user)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('comments.data.0.is_resolved')
            ->has('comments.data.1.is_resolved')
        );
    }

    #[Group('comments')]
    #[Test]
    public function comment_resource_includes_can_resolve_for_officers(): void
    {
        $this->mockItemService();

        $item = $this->createTestItem();
        $officer = User::factory()->officer()->create();
        $commentAuthor = User::factory()->raider()->create();
        Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($officer)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('comments.data.0.permissions.resolve', true)
        );
    }

    #[Group('comments')]
    #[Test]
    public function comment_resource_includes_can_resolve_false_for_raiders(): void
    {
        $this->mockItemService();

        $item = $this->createTestItem();
        $raider = User::factory()->raider()->create();
        $commentAuthor = User::factory()->raider()->create();
        Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($raider)->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('comments.data.0.permissions.resolve', false)
        );
    }

    // ==================== show — cross-raid items ====================

    #[Test]
    public function a_cross_raid_item_exposes_only_one_raid_to_the_page(): void
    {
        $raids = Raid::factory()->count(2)->create();
        $item = Item::factory()
            ->trashDrop()
            ->withName('Test Item')
            ->inRaids($raids->all())
            ->create();

        $this->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('item.data.raids', 1)
            );
    }

    #[Test]
    public function show_returns_the_remembered_origin_raid_when_the_item_drops_there(): void
    {
        $raids = Raid::factory()->count(2)->create();
        $item = Item::factory()
            ->trashDrop()
            ->withName('Test Item')
            ->inRaids($raids->all())
            ->create();

        $this->withSession([SharesOriginRaidSession::SESSION_KEY => $raids[1]->id])
            ->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('item.data.raids.0.id', $raids[1]->id)
            );
    }

    #[Test]
    public function show_falls_back_to_the_first_raid_when_the_remembered_raid_does_not_apply(): void
    {
        $raids = Raid::factory()->count(2)->create();
        $otherRaid = Raid::factory()->create();
        $item = Item::factory()
            ->trashDrop()
            ->withName('Test Item')
            ->inRaids($raids->all())
            ->create();
        $firstRaidId = $raids->sortBy('id')->first()->id;

        $this->withSession([SharesOriginRaidSession::SESSION_KEY => $otherRaid->id])
            ->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('item.data.raids.0.id', $firstRaidId)
            );
    }

    #[Test]
    public function show_falls_back_to_the_first_raid_when_nothing_is_remembered(): void
    {
        $raids = Raid::factory()->count(2)->create();
        $item = Item::factory()
            ->trashDrop()
            ->withName('Test Item')
            ->inRaids($raids->all())
            ->create();
        $firstRaidId = $raids->sortBy('id')->first()->id;

        $this->get(route('loot.items.show', ['item' => $item->id, 'slug' => $item->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('item.data.raids.0.id', $firstRaidId)
            );
    }

    // ==================== helpers ====================

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
