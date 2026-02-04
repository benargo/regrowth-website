<?php

namespace Tests\Feature\LootCouncil;

use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemComment;
use App\Models\TBC\Boss;
use App\Models\TBC\Phase;
use App\Models\TBC\Raid;
use App\Models\User;
use App\Services\Blizzard\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class CommentCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();
    }

    protected function mockItemService(): void
    {
        $this->instance(
            ItemService::class,
            Mockery::mock(ItemService::class, function (MockInterface $mock) {
                $mock->shouldReceive('find')
                    ->andReturnUsing(fn (int $id) => [
                        'id' => $id,
                        'name' => "Test Item {$id}",
                    ]);

                $mock->shouldReceive('media')
                    ->andReturn([
                        'assets' => [
                            ['key' => 'icon', 'value' => 'https://example.com/icon.jpg'],
                        ],
                    ]);
            })
        );
    }

    protected function createItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        return Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
    }

    // ==========================================
    // Cache population tests
    // ==========================================

    public function test_viewing_item_caches_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->member()->create();
        $commentAuthor = User::factory()->raider()->create();
        ItemComment::factory()->count(3)->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);

        Cache::tags(["item_{$item->id}_comments"])->flush();

        $this->assertFalse(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_1")
        );

        $this->actingAs($user)->get(route('loot.items.show', $item));

        $this->assertTrue(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_1")
        );
    }

    public function test_viewing_item_edit_page_caches_comments(): void
    {
        $item = $this->createItem();
        $user = User::factory()->officer()->create();
        $commentAuthor = User::factory()->raider()->create();
        ItemComment::factory()->count(3)->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);

        Cache::tags(["item_{$item->id}_comments"])->flush();

        $this->assertFalse(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_1")
        );

        $this->actingAs($user)->get(route('loot.items.edit', $item));

        $this->assertTrue(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_1")
        );
    }

    public function test_cached_comments_are_returned_on_subsequent_requests(): void
    {
        $item = $this->createItem();
        $user = User::factory()->member()->create();
        $commentAuthor = User::factory()->raider()->create();
        ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
            'body' => 'Original cached comment',
        ]);

        // First request populates the cache
        $this->actingAs($user)->get(route('loot.items.show', $item));

        // Directly modify the database without going through the controller
        // This simulates what happens when cache is stale
        ItemComment::where('item_id', $item->id)->update(['body' => 'Modified comment']);

        // Second request should return cached data (still showing original)
        $response = $this->actingAs($user)->get(route('loot.items.show', $item));

        $response->assertOk();
        $response->assertSee('Original cached comment');
    }

    // ==========================================
    // Cache invalidation on create
    // ==========================================

    public function test_creating_comment_invalidates_cache(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        // Populate the cache
        $this->actingAs($user)->get(route('loot.items.show', $item));

        $this->assertTrue(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_1")
        );

        // Create a comment
        $this->actingAs($user)->post(route('loot.items.comments.store', $item), [
            'body' => 'New comment that should invalidate cache',
        ]);

        $this->assertFalse(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_1")
        );
    }

    public function test_new_comment_appears_after_cache_invalidation(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        // First request populates cache with no comments
        $this->actingAs($user)->get(route('loot.items.show', $item));

        // Create a new comment
        $this->actingAs($user)->post(route('loot.items.comments.store', $item), [
            'body' => 'Brand new comment',
        ]);

        // Next request should show the new comment
        $response = $this->actingAs($user)->get(route('loot.items.show', $item));

        $response->assertOk();
        $response->assertSee('Brand new comment');
    }

    // ==========================================
    // Cache invalidation on update
    // ==========================================

    public function test_updating_comment_invalidates_cache(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'Original comment',
        ]);

        // Populate the cache
        $this->actingAs($user)->get(route('loot.items.show', $item));

        $this->assertTrue(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_1")
        );

        // Update the comment
        $this->actingAs($user)->put(route('loot.items.comments.update', [$item, $comment]), [
            'body' => 'Updated comment',
        ]);

        $this->assertFalse(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_1")
        );
    }

    public function test_updated_comment_appears_after_cache_invalidation(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'Original comment body',
        ]);

        // First request populates the cache
        $this->actingAs($user)->get(route('loot.items.show', $item));

        // Update the comment
        $this->actingAs($user)->put(route('loot.items.comments.update', [$item, $comment]), [
            'body' => 'Updated comment body',
        ]);

        // Next request should show the updated comment
        $response = $this->actingAs($user)->get(route('loot.items.show', $item));

        $response->assertOk();
        $response->assertSee('Updated comment body');
    }

    // ==========================================
    // Cache invalidation on delete
    // ==========================================

    public function test_deleting_comment_invalidates_cache(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
        ]);

        // Populate the cache
        $this->actingAs($user)->get(route('loot.items.show', $item));

        $this->assertTrue(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_1")
        );

        // Delete the comment
        $this->actingAs($user)->delete(route('loot.items.comments.destroy', [$item, $comment]));

        $this->assertFalse(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_1")
        );
    }

    public function test_deleted_comment_disappears_after_cache_invalidation(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();
        $comment = ItemComment::factory()->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'Comment to be deleted',
        ]);

        // First request populates the cache
        $response = $this->actingAs($user)->get(route('loot.items.show', $item));
        $response->assertSee('Comment to be deleted');

        // Delete the comment
        $this->actingAs($user)->delete(route('loot.items.comments.destroy', [$item, $comment]));

        // Next request should not show the deleted comment
        $response = $this->actingAs($user)->get(route('loot.items.show', $item));

        $response->assertOk();
        $response->assertDontSee('Comment to be deleted');
    }

    // ==========================================
    // Cache isolation tests
    // ==========================================

    public function test_cache_invalidation_only_affects_specific_item(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();
        $user = User::factory()->raider()->create();

        ItemComment::factory()->create([
            'item_id' => $item1->id,
            'user_id' => $user->id,
        ]);
        ItemComment::factory()->create([
            'item_id' => $item2->id,
            'user_id' => $user->id,
        ]);

        // Populate cache for both items
        $this->actingAs($user)->get(route('loot.items.show', $item1));
        $this->actingAs($user)->get(route('loot.items.show', $item2));

        $this->assertTrue(
            Cache::tags(["item_{$item1->id}_comments"])->has("item_{$item1->id}_comments_page_1")
        );
        $this->assertTrue(
            Cache::tags(["item_{$item2->id}_comments"])->has("item_{$item2->id}_comments_page_1")
        );

        // Create a comment on item1
        $this->actingAs($user)->post(route('loot.items.comments.store', $item1), [
            'body' => 'New comment on item 1',
        ]);

        // Item1 cache should be invalidated, item2 cache should remain
        $this->assertFalse(
            Cache::tags(["item_{$item1->id}_comments"])->has("item_{$item1->id}_comments_page_1")
        );
        $this->assertTrue(
            Cache::tags(["item_{$item2->id}_comments"])->has("item_{$item2->id}_comments_page_1")
        );
    }

    // ==========================================
    // Pagination cache tests
    // ==========================================

    public function test_different_pages_are_cached_separately(): void
    {
        $item = $this->createItem();
        $user = User::factory()->member()->create();
        $commentAuthor = User::factory()->raider()->create();

        // Create enough comments for multiple pages
        ItemComment::factory()->count(15)->create([
            'item_id' => $item->id,
            'user_id' => $commentAuthor->id,
        ]);

        // Visit page 1
        $this->actingAs($user)->get(route('loot.items.show', $item));

        // Visit page 2
        $this->actingAs($user)->get(route('loot.items.show', ['item' => $item, 'page' => 2]));

        $this->assertTrue(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_1")
        );
        $this->assertTrue(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_2")
        );
    }

    public function test_cache_invalidation_clears_all_pages(): void
    {
        $item = $this->createItem();
        $user = User::factory()->raider()->create();

        // Create enough comments for multiple pages
        ItemComment::factory()->count(15)->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
        ]);

        // Visit multiple pages to cache them
        $this->actingAs($user)->get(route('loot.items.show', $item));
        $this->actingAs($user)->get(route('loot.items.show', ['item' => $item, 'page' => 2]));

        $this->assertTrue(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_1")
        );
        $this->assertTrue(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_2")
        );

        // Create a new comment (should invalidate all pages)
        $this->actingAs($user)->post(route('loot.items.comments.store', $item), [
            'body' => 'New comment',
        ]);

        $this->assertFalse(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_1")
        );
        $this->assertFalse(
            Cache::tags(["item_{$item->id}_comments"])->has("item_{$item->id}_comments_page_2")
        );
    }
}
