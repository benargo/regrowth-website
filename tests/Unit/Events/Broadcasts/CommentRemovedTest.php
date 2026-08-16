<?php

namespace Tests\Unit\Events\Broadcasts;

use App\Events\Broadcasts\CommentRemoved;
use App\Models\Comment;
use App\Models\Item;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('comments')]
#[Group('broadcasting')]
class CommentRemovedTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_broadcasts_on_the_commentables_public_channel(): void
    {
        $comment = $this->commentOn($this->itemWithId(42));

        $channels = (new CommentRemoved($comment))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('item.42', $channels[0]->name);
    }

    #[Test]
    #[Group('contract')]
    public function it_uses_a_plain_public_channel_not_a_private_channel(): void
    {
        $comment = $this->commentOn($this->itemWithId(1));

        $channels = (new CommentRemoved($comment))->broadcastOn();

        $this->assertNotInstanceOf(PrivateChannel::class, $channels[0]);
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_no_channels_when_the_commentable_does_not_implement_commentable(): void
    {
        $comment = $this->commentOn(new User(['id' => 1]));

        $this->assertSame([], (new CommentRemoved($comment))->broadcastOn());
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_as_comment_removed(): void
    {
        $comment = $this->commentOn($this->itemWithId(1));

        $this->assertSame('CommentRemoved', (new CommentRemoved($comment))->broadcastAs());
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_the_comment_id_parent_id_and_root_flag(): void
    {
        $comment = $this->commentOn($this->itemWithId(1));
        $comment->id = 77;

        $this->assertSame(
            ['comment_id' => 77, 'parent_id' => null, 'is_root' => true],
            (new CommentRemoved($comment))->broadcastWith(),
        );
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_immediately(): void
    {
        $comment = $this->commentOn($this->itemWithId(1));

        $this->assertInstanceOf(ShouldBroadcastNow::class, new CommentRemoved($comment));
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_a_removed_root_as_a_root(): void
    {
        $comment = $this->commentOn($this->itemWithId(1));

        $payload = (new CommentRemoved($comment))->broadcastWith();

        $this->assertNull($payload['parent_id']);
        $this->assertTrue($payload['is_root']);
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_a_removed_reply_with_its_root(): void
    {
        $reply = $this->commentOn($this->itemWithId(1));
        $reply->parent_id = 7;

        $payload = (new CommentRemoved($reply))->broadcastWith();

        $this->assertSame(7, $payload['parent_id']);
        $this->assertFalse($payload['is_root']);
    }

    /**
     * Build an in-memory (unsaved) item with the given ID.
     */
    private function itemWithId(int $id): Item
    {
        $item = new Item;
        $item->id = $id;

        return $item;
    }

    /**
     * Build an in-memory (unsaved) comment attached to the given commentable.
     */
    private function commentOn(object $commentable): Comment
    {
        $comment = new Comment(['body' => 'A comment']);
        $comment->setRelation('commentable', $commentable);

        return $comment;
    }
}
