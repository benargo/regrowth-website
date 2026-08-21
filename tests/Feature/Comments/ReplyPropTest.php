<?php

namespace Tests\Feature\Comments;

use App\Http\Controllers\ItemController;
use App\Models\Comment;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('comments')]
#[Group('loot')]
class ReplyPropTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockDiscordChannel()->shouldReceive('createMessage')->andReturn($this->makeDiscordMessage());
    }

    #[Test]
    public function the_comments_prop_lists_only_root_comments(): void
    {
        $item = Item::factory()->create();
        $root = $this->rootOn($item);
        Comment::factory()->replyTo($root)->count(2)->create();

        $response = $this->get($this->itemUrl($item));

        $response->assertInertia(fn (AssertableJson $page) => $page
            ->has('comments.data', 1)
            ->where('comments.data.0.id', $root->id)
        );
    }

    #[Test]
    public function each_root_carries_at_most_five_replies_with_a_true_count(): void
    {
        $item = Item::factory()->create();
        $root = $this->rootOn($item);

        foreach (range(1, 8) as $index) {
            Comment::factory()->replyTo($root)->create([
                'created_at' => now()->subMinutes(100 - $index),
            ]);
        }

        $response = $this->get($this->itemUrl($item));

        $response->assertInertia(fn (AssertableJson $page) => $page
            ->has('comments.data.0.replies', 5)
            ->where('comments.data.0.replies_count', 8)
        );
    }

    #[Test]
    public function replies_are_ordered_oldest_first(): void
    {
        $item = Item::factory()->create();
        $root = $this->rootOn($item);

        $older = Comment::factory()->replyTo($root)->create(['created_at' => now()->subHour()]);
        $newer = Comment::factory()->replyTo($root)->create(['created_at' => now()]);

        $response = $this->get($this->itemUrl($item));

        $response->assertInertia(fn (AssertableJson $page) => $page
            ->where('comments.data.0.replies.0.id', $older->id)
            ->where('comments.data.0.replies.1.id', $newer->id)
        );
    }

    #[Test]
    public function a_root_with_fewer_than_five_replies_carries_all_of_them(): void
    {
        $item = Item::factory()->create();
        $root = $this->rootOn($item);
        Comment::factory()->replyTo($root)->count(3)->create();

        $response = $this->get($this->itemUrl($item));

        $response->assertInertia(fn (AssertableJson $page) => $page
            ->has('comments.data.0.replies', 3)
            ->where('comments.data.0.replies_count', 3)
        );
    }

    // ==================== tombstoned roots ====================

    #[Test]
    public function a_trashed_root_with_live_replies_is_still_listed_as_a_tombstone(): void
    {
        $item = Item::factory()->create();
        $root = $this->rootOn($item);
        Comment::factory()->replyTo($root)->create();
        $root->delete();

        $response = $this->get($this->itemUrl($item));

        $response->assertInertia(fn (AssertableJson $page) => $page
            ->has('comments.data', 1)
            ->where('comments.data.0.is_deleted', true)
            ->where('comments.data.0.body', null)
        );
    }

    #[Test]
    public function a_trashed_root_with_no_live_replies_is_not_listed(): void
    {
        $item = Item::factory()->create();
        $root = $this->rootOn($item);
        $root->delete();

        $response = $this->get($this->itemUrl($item));

        $response->assertInertia(fn (AssertableJson $page) => $page->has('comments.data', 0));
    }

    // ==================== partial reload pagination ====================

    #[Test]
    public function the_replies_prop_is_absent_from_the_initial_render(): void
    {
        $item = Item::factory()->create();
        $root = $this->rootOn($item);
        Comment::factory()->replyTo($root)->count(8)->create();

        $response = $this->get($this->itemUrl($item));

        $response->assertInertia(fn (AssertableJson $page) => $page->missing('replies'));
    }

    #[Test]
    public function the_replies_prop_returns_the_next_page_for_a_root(): void
    {
        $item = Item::factory()->create();
        $root = $this->rootOn($item);

        $replies = collect(range(1, 8))->map(fn (int $index) => Comment::factory()
            ->replyTo($root)
            ->create(['created_at' => now()->subMinutes(100 - $index)]));

        $response = $this->partialReload($item, [$root->id => 5]);

        $response->assertJsonCount(3, "props.replies.{$root->id}");
        $response->assertJsonPath("props.replies.{$root->id}.0.id", $replies[5]->id);
    }

    #[Test]
    public function the_replies_prop_batches_several_roots_in_one_request(): void
    {
        $item = Item::factory()->create();
        $first = $this->rootOn($item);
        $second = $this->rootOn($item);

        Comment::factory()->replyTo($first)->count(7)->create();
        Comment::factory()->replyTo($second)->count(6)->create();

        $response = $this->partialReload($item, [$first->id => 5, $second->id => 5]);

        $response->assertJsonCount(2, "props.replies.{$first->id}");
        $response->assertJsonCount(1, "props.replies.{$second->id}");
    }

    #[Test]
    public function a_root_already_fully_loaded_returns_no_entry(): void
    {
        $item = Item::factory()->create();
        $root = $this->rootOn($item);
        Comment::factory()->replyTo($root)->count(3)->create();

        $response = $this->partialReload($item, [$root->id => 5]);

        $response->assertJsonMissingPath("props.replies.{$root->id}");
    }

    #[Test]
    public function root_ids_from_another_item_return_nothing(): void
    {
        $item = Item::factory()->create();
        $otherItem = Item::factory()->create();
        $foreignRoot = $this->rootOn($otherItem);
        Comment::factory()->replyTo($foreignRoot)->count(8)->create();

        $response = $this->partialReload($item, [$foreignRoot->id => 0]);

        $response->assertJsonMissingPath("props.replies.{$foreignRoot->id}");
    }

    #[Test]
    public function unknown_and_non_root_ids_are_silently_omitted(): void
    {
        $item = Item::factory()->create();
        $root = $this->rootOn($item);
        $reply = Comment::factory()->replyTo($root)->create();

        $response = $this->partialReload($item, [999999 => 0, $reply->id => 0]);

        $response->assertJsonMissingPath('props.replies.999999');
        $response->assertJsonMissingPath("props.replies.{$reply->id}");
    }

    // ==================== authorization and limits ====================

    #[Test]
    #[Group('authorization')]
    public function a_guest_can_load_replies_but_cannot_reply(): void
    {
        $item = Item::factory()->create();
        $root = $this->rootOn($item);
        Comment::factory()->replyTo($root)->count(8)->create();

        $response = $this->partialReload($item, [$root->id => 5]);

        $response->assertOk();
        $response->assertJsonCount(3, "props.replies.{$root->id}");
        $response->assertJsonPath("props.replies.{$root->id}.0.permissions.reply", false);
    }

    #[Test]
    public function the_replies_prop_caps_the_number_of_requested_roots(): void
    {
        $item = Item::factory()->create();
        $offsets = collect(range(1, 60))
            ->mapWithKeys(function () use ($item): array {
                $root = $this->rootOn($item);
                Comment::factory()->replyTo($root)->create();

                return [$root->id => 0];
            })
            ->all();

        $response = $this->partialReload($item, $offsets);

        $this->assertLessThanOrEqual(
            ItemController::MAX_REPLY_ROOTS,
            count($response->json('props.replies')),
        );
    }

    // ==================== helpers ====================

    /**
     * Create a root comment attached to the given item.
     */
    private function rootOn(Item $item): Comment
    {
        return Comment::factory()->create([
            'commentable_id' => $item->id,
            'commentable_type' => Item::class,
        ]);
    }

    /**
     * Build the canonical show URL for an item.
     */
    private function itemUrl(Item $item): string
    {
        return route('loot.items.show', [
            'item' => $item->id,
            'slug' => $item->slug ?: "item-{$item->id}",
        ]);
    }

    /**
     * Issue an Inertia partial reload requesting only the `replies` prop.
     *
     * @param  array<int, int>  $offsets
     */
    private function partialReload(Item $item, array $offsets): TestResponse
    {
        $initial = $this->get($this->itemUrl($item));
        $version = $initial->viewData('page')['version'];

        return $this->get($this->itemUrl($item).'?'.http_build_query(['offsets' => $offsets]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'Loot/Items/Show',
            'X-Inertia-Partial-Data' => 'replies',
        ]);
    }
}
