<?php

namespace Tests\Feature\Broadcasting;

use App\Events\Broadcasts\CommentReactionChanged;
use App\Models\Boss;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\Item;
use App\Models\User;
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
class CommentReactionBroadcastTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[Group('contract')]
    public function comment_reaction_changed_broadcasts_on_the_public_item_channel(): void
    {
        $item = Item::factory()->create();
        $comment = $this->commentOn($item);
        $reaction = CommentReaction::factory()->forComment($comment)->create();

        $channels = (new CommentReactionChanged($reaction, 'created'))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertNotInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals("item.{$item->id}", $channels[0]->name);
    }

    #[Test]
    #[Group('contract')]
    public function comment_reaction_changed_broadcasts_as_comment_reaction_changed(): void
    {
        $comment = $this->commentOn(Item::factory()->create());
        $reaction = CommentReaction::factory()->forComment($comment)->create();

        $this->assertEquals(
            'CommentReactionChanged',
            (new CommentReactionChanged($reaction, 'created'))->broadcastAs(),
        );
    }

    #[Test]
    #[Group('contract')]
    public function comment_reaction_changed_created_payload_includes_the_reaction(): void
    {
        $comment = $this->commentOn(Item::factory()->create());
        $reactor = User::factory()->create();
        $reaction = CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();
        $reaction->load('user');

        $payload = (new CommentReactionChanged($reaction, 'created'))->broadcastWith();

        $this->assertEquals($comment->id, $payload['comment_id']);
        $this->assertEquals('created', $payload['action']);
        $this->assertArrayHasKey('reaction', $payload);
        $this->assertEquals($reaction->id, $payload['reaction']['id']);
    }

    #[Test]
    #[Group('contract')]
    public function comment_reaction_changed_deleted_payload_omits_the_reaction(): void
    {
        $comment = $this->commentOn(Item::factory()->create());
        $reaction = CommentReaction::factory()->forComment($comment)->create();

        $payload = (new CommentReactionChanged($reaction, 'deleted'))->broadcastWith();

        $this->assertEquals(
            ['comment_id' => $comment->id, 'action' => 'deleted'],
            $payload,
        );
        $this->assertArrayNotHasKey('reaction', $payload);
    }

    #[Test]
    #[Group('contract')]
    public function comment_reaction_changed_does_not_build_an_item_channel_for_a_non_item_commentable(): void
    {
        $boss = Boss::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_type' => Boss::class,
            'commentable_id' => (string) $boss->id,
        ]);
        $reaction = CommentReaction::factory()->forComment($comment)->create();

        $this->assertSame([], (new CommentReactionChanged($reaction, 'created'))->broadcastOn());
    }

    #[Test]
    #[Group('contract')]
    public function comment_reaction_changed_yields_no_channel_when_its_comment_is_gone(): void
    {
        $comment = $this->commentOn(Item::factory()->create());
        $reaction = CommentReaction::factory()->forComment($comment)->create();

        $comment->forceDelete();
        $reaction->unsetRelation('comment');

        $this->assertSame([], (new CommentReactionChanged($reaction, 'deleted'))->broadcastOn());
    }

    #[Test]
    #[Group('contract')]
    public function comment_reaction_changed_broadcasts_immediately(): void
    {
        $comment = $this->commentOn(Item::factory()->create());
        $reaction = CommentReaction::factory()->forComment($comment)->create();

        $this->assertInstanceOf(ShouldBroadcastNow::class, new CommentReactionChanged($reaction, 'created'));
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
