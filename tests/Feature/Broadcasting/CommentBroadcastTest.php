<?php

namespace Tests\Feature\Broadcasting;

use App\Events\Broadcasts\CommentChanged;
use App\Events\Broadcasts\CommentPosted;
use App\Events\Broadcasts\CommentRemoved;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\Item;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('comments')]
#[Group('loot')]
#[Group('broadcasting')]
class CommentBroadcastTest extends TestCase
{
    use RefreshDatabase;

    // ─── CommentPosted ───────────────────────────────────────────────────────

    #[Test]
    #[Group('contract')]
    public function comment_posted_broadcasts_on_the_public_item_channel(): void
    {
        $item = Item::factory()->create();
        $comment = $this->commentOn($item);

        $channels = (new CommentPosted($comment))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertNotInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("item.{$item->id}", $channels[0]->name);
    }

    #[Test]
    #[Group('contract')]
    public function comment_posted_broadcasts_as_comment_posted(): void
    {
        $comment = $this->commentOn(Item::factory()->create());

        $this->assertEquals('CommentPosted', (new CommentPosted($comment))->broadcastAs());
    }

    #[Test]
    #[Group('contract')]
    public function comment_posted_payload_carries_the_comment_resource(): void
    {
        $comment = $this->commentOn(Item::factory()->create());
        $comment->load('user', 'reactions.user');

        $payload = (new CommentPosted($comment))->broadcastWith();

        $this->assertSame(['comment', 'parent_id'], array_keys($payload));
        $this->assertEquals($comment->id, $payload['comment']['id']);
        $this->assertEquals($comment->body, $payload['comment']['body']);
        $this->assertFalse($payload['comment']['is_resolved']);
        $this->assertArrayHasKey('reactions', $payload['comment']);
        $this->assertArrayHasKey('permissions', $payload['comment']);
        $this->assertNull($payload['parent_id']);
    }

    // ─── CommentChanged ──────────────────────────────────────────────────────

    #[Test]
    #[Group('contract')]
    public function comment_changed_broadcasts_on_the_public_item_channel(): void
    {
        $item = Item::factory()->create();
        $comment = $this->commentOn($item);

        $channels = (new CommentChanged($comment))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertNotInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("item.{$item->id}", $channels[0]->name);
    }

    #[Test]
    #[Group('contract')]
    public function comment_changed_broadcasts_as_comment_changed(): void
    {
        $comment = $this->commentOn(Item::factory()->create());

        $this->assertEquals('CommentChanged', (new CommentChanged($comment))->broadcastAs());
    }

    #[Test]
    #[Group('contract')]
    public function comment_changed_payload_reflects_the_resolved_state(): void
    {
        $item = Item::factory()->create();
        $comment = Comment::factory()->resolved()->create([
            'commentable_type' => Item::class,
            'commentable_id' => (string) $item->id,
        ]);
        $comment->load('user', 'reactions.user');

        $payload = (new CommentChanged($comment))->broadcastWith();

        $this->assertSame(['comment'], array_keys($payload));
        $this->assertEquals($comment->id, $payload['comment']['id']);
        $this->assertTrue($payload['comment']['is_resolved']);
    }

    // ─── CommentRemoved ──────────────────────────────────────────────────────

    #[Test]
    #[Group('contract')]
    public function comment_removed_broadcasts_on_the_public_item_channel(): void
    {
        $item = Item::factory()->create();
        $comment = $this->commentOn($item);

        $channels = (new CommentRemoved($comment))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertNotInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("item.{$item->id}", $channels[0]->name);
    }

    #[Test]
    #[Group('contract')]
    public function comment_removed_broadcasts_as_comment_removed(): void
    {
        $comment = $this->commentOn(Item::factory()->create());

        $this->assertEquals('CommentRemoved', (new CommentRemoved($comment))->broadcastAs());
    }

    #[Test]
    #[Group('contract')]
    public function comment_removed_payload_carries_the_comment_id_parent_id_and_root_flag(): void
    {
        $comment = $this->commentOn(Item::factory()->create());

        $this->assertEquals(
            ['comment_id' => $comment->id, 'parent_id' => null, 'is_root' => true],
            (new CommentRemoved($comment))->broadcastWith(),
        );
    }

    #[Test]
    #[Group('contract')]
    public function comment_removed_still_resolves_its_channel_after_a_soft_delete(): void
    {
        $item = Item::factory()->create();
        $comment = $this->commentOn($item);

        $comment->delete();

        $channels = (new CommentRemoved($comment))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals("item.{$item->id}", $channels[0]->name);
    }

    // ─── The non-Item guard ──────────────────────────────────────────────────

    #[Test]
    #[Group('contract')]
    public function comment_events_do_not_build_an_item_channel_for_a_non_item_commentable(): void
    {
        $boss = Boss::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Boss::class,
            'commentable_id' => (string) $boss->id,
        ]);

        $this->assertSame([], (new CommentPosted($comment))->broadcastOn());
        $this->assertSame([], (new CommentChanged($comment))->broadcastOn());
        $this->assertSame([], (new CommentRemoved($comment))->broadcastOn());
    }

    // ─── Contract ────────────────────────────────────────────────────────────

    #[Test]
    #[Group('contract')]
    public function all_three_events_broadcast_immediately(): void
    {
        $comment = $this->commentOn(Item::factory()->create());

        $this->assertInstanceOf(ShouldBroadcastNow::class, new CommentPosted($comment));
        $this->assertInstanceOf(ShouldBroadcastNow::class, new CommentChanged($comment));
        $this->assertInstanceOf(ShouldBroadcastNow::class, new CommentRemoved($comment));
    }

    /**
     * Build a comment attached to the given item.
     */
    private function commentOn(Item $item): Comment
    {
        return Comment::factory()->create([
            'commentable_type' => Item::class,
            'commentable_id' => (string) $item->id,
        ]);
    }
}
