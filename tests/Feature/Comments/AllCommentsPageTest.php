<?php

namespace Tests\Feature\Comments;

use App\Models\Comment;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('comments')]
#[Group('loot')]
class AllCommentsPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_users_can_access_comments_index(): void
    {
        $response = $this->get(route('loot.comments'));

        $response->assertOk();
    }

    #[Test]
    public function loot_councillors_can_access_comments_index(): void
    {
        $user = User::factory()->member()->lootCouncillor()->create();

        $response = $this->actingAs($user)->get(route('loot.comments'));

        $response->assertOk();
    }

    #[Test]
    public function officers_can_access_comments_index(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->get(route('loot.comments'));

        $response->assertOk();
    }

    // ==================== pagination and item data ====================

    #[Test]
    public function comments_index_is_paginated(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();
        $user = User::factory()->officer()->create();
        $commentAuthor = User::factory()->raider()->create();

        // Create 25 comments across two items
        Comment::factory()->count(15)->create([
            'commentable_id' => (string) $item1->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);
        Comment::factory()->count(10)->create([
            'commentable_id' => (string) $item2->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($user)->get(route('loot.comments'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Comments')
            ->has('comments.data', 20) // 20 per page
            ->has('comments.links')
            ->has('comments.meta')
        );
    }

    #[Test]
    public function comments_index_includes_item_data(): void
    {
        $item1 = $this->createItem();
        $item2 = $this->createItem();
        $user = User::factory()->officer()->create();
        $commentAuthor = User::factory()->raider()->create();

        Comment::factory()->count(3)->create([
            'commentable_id' => (string) $item1->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);
        Comment::factory()->count(2)->create([
            'commentable_id' => (string) $item2->id,
            'commentable_type' => Item::class,
            'user_id' => $commentAuthor->id,
        ]);

        $response = $this->actingAs($user)->get(route('loot.comments'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Comments')
            ->has('comments.data', 5)
            ->has('comments.data.0.commentable')
            ->has('comments.data.0.user')
            ->has('comments.data.0.permissions')
        );
    }

    // ==================== ordering ====================

    #[Test]
    public function comments_index_orders_by_latest(): void
    {
        $item = $this->createItem();
        $user = User::factory()->officer()->create();
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

        $response = $this->actingAs($user)->get(route('loot.comments'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('comments.data.0.id', $newComment->id)
            ->where('comments.data.1.id', $oldComment->id)
        );
    }

    // ==================== root comments and reply pagination ====================

    #[Test]
    public function the_index_lists_only_root_comments(): void
    {
        $root = Comment::factory()->create();
        Comment::factory()->replyTo($root)->count(2)->create();

        $response = $this->actingAs(User::factory()->officer()->create())->get(route('loot.comments'));

        $response->assertInertia(fn (AssertableJson $page) => $page
            ->has('comments.data', 1)
            ->where('comments.data.0.id', $root->id)
            ->where('comments.data.0.replies_count', 2)
            ->etc()
        );
    }

    #[Test]
    public function the_index_carries_the_first_page_of_replies_per_root(): void
    {
        $root = Comment::factory()->create();

        foreach (range(1, 8) as $index) {
            Comment::factory()->replyTo($root)->create([
                'created_at' => now()->subMinutes(100 - $index),
            ]);
        }

        $response = $this->actingAs(User::factory()->officer()->create())->get(route('loot.comments'));

        $response->assertInertia(fn (AssertableJson $page) => $page
            ->has('comments.data.0.replies', 5)
            ->where('comments.data.0.replies_count', 8)
            ->etc()
        );
    }

    #[Test]
    public function the_index_replies_prop_is_absent_until_requested(): void
    {
        $root = Comment::factory()->create();
        Comment::factory()->replyTo($root)->count(8)->create();

        $response = $this->actingAs(User::factory()->officer()->create())->get(route('loot.comments'));

        $response->assertInertia(fn (AssertableJson $page) => $page->missing('replies'));
    }

    // ==================== tombstoned roots ====================

    #[Test]
    public function a_trashed_root_with_live_replies_is_still_listed_as_a_tombstone(): void
    {
        $root = Comment::factory()->create();
        Comment::factory()->replyTo($root)->create();
        $root->delete();

        $response = $this->actingAs(User::factory()->officer()->create())->get(route('loot.comments'));

        $response->assertInertia(fn (AssertableJson $page) => $page
            ->has('comments.data', 1)
            ->where('comments.data.0.is_deleted', true)
            ->where('comments.data.0.body', null)
        );
    }

    #[Test]
    public function a_trashed_root_with_no_live_replies_is_not_listed(): void
    {
        $root = Comment::factory()->create();
        $root->delete();

        $response = $this->actingAs(User::factory()->officer()->create())->get(route('loot.comments'));

        $response->assertInertia(fn (AssertableJson $page) => $page->has('comments.data', 0));
    }

    #[Test]
    public function the_index_replies_prop_returns_the_next_page(): void
    {
        $root = Comment::factory()->create();

        $replies = collect(range(1, 8))->map(fn (int $index) => Comment::factory()
            ->replyTo($root)
            ->create(['created_at' => now()->subMinutes(100 - $index)]));

        $response = $this->partialReload([$root->id => 5]);

        $response->assertJsonCount(3, "props.replies.{$root->id}");
        $response->assertJsonPath("props.replies.{$root->id}.0.id", $replies[5]->id);
    }

    #[Test]
    public function the_index_replies_prop_still_paginates_a_tombstoned_root(): void
    {
        $root = Comment::factory()->create();

        $replies = collect(range(1, 8))->map(fn (int $index) => Comment::factory()
            ->replyTo($root)
            ->create(['created_at' => now()->subMinutes(100 - $index)]));

        $root->delete();

        $response = $this->partialReload([$root->id => 5]);

        $response->assertJsonCount(3, "props.replies.{$root->id}");
        $response->assertJsonPath("props.replies.{$root->id}.0.id", $replies[5]->id);
    }

    #[Test]
    public function the_index_replies_prop_omits_non_root_ids(): void
    {
        $root = Comment::factory()->create();
        $reply = Comment::factory()->replyTo($root)->create();

        $response = $this->partialReload([$reply->id => 0, 999999 => 0]);

        $response->assertJsonMissingPath("props.replies.{$reply->id}");
        $response->assertJsonMissingPath('props.replies.999999');
    }

    #[Test]
    public function the_index_lists_a_tombstoned_root_that_still_has_live_replies(): void
    {
        $user = User::factory()->officer()->create();
        $root = Comment::factory()->create();
        Comment::factory()->replyTo($root)->create();
        $root->delete();

        $response = $this->actingAs($user)->get(route('loot.comments'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Comments')
            ->has('comments.data', 1)
            ->where('comments.data.0.id', $root->id)
            ->where('comments.data.0.is_deleted', true)
            ->where('comments.data.0.body', null)
            ->where('comments.data.0.replies_count', 1)
        );
    }

    #[Test]
    public function the_index_omits_a_tombstoned_root_with_no_live_replies(): void
    {
        $user = User::factory()->officer()->create();
        $root = Comment::factory()->create();
        $root->delete();

        $response = $this->actingAs($user)->get(route('loot.comments'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Loot/Comments')
            ->has('comments.data', 0)
        );
    }

    // ==================== helpers ====================

    protected function createItem(): Item
    {
        return Item::factory()->fromBoss()->withName('Test Item')->create();
    }

    /**
     * Issue an Inertia partial reload requesting only the `replies` prop.
     *
     * @param  array<int, int>  $offsets
     */
    private function partialReload(array $offsets): TestResponse
    {
        $initial = $this->actingAs(User::factory()->officer()->create())->get(route('loot.comments'));
        $version = $initial->viewData('page')['version'];

        return $this->get(route('loot.comments').'?'.http_build_query(['offsets' => $offsets]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'Loot/Comments',
            'X-Inertia-Partial-Data' => 'replies',
        ]);
    }
}
