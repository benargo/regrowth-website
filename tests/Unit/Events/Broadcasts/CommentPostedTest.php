<?php

namespace Tests\Unit\Events\Broadcasts;

use App\Events\Broadcasts\CommentPosted;
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
class CommentPostedTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_broadcasts_on_the_commentables_public_channel(): void
    {
        $item = $this->itemWithId(42);
        $comment = $this->commentOn($item);

        $channels = (new CommentPosted($comment))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('item.42', $channels[0]->name);
    }

    #[Test]
    #[Group('contract')]
    public function it_uses_a_plain_public_channel_not_a_private_channel(): void
    {
        $comment = $this->commentOn($this->itemWithId(1));

        $channels = (new CommentPosted($comment))->broadcastOn();

        $this->assertNotInstanceOf(PrivateChannel::class, $channels[0]);
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_no_channels_when_the_commentable_does_not_implement_commentable(): void
    {
        $comment = $this->commentOn(new User(['id' => 1]));

        $this->assertSame([], (new CommentPosted($comment))->broadcastOn());
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_as_comment_posted(): void
    {
        $comment = $this->commentOn($this->itemWithId(1));

        $this->assertSame('CommentPosted', (new CommentPosted($comment))->broadcastAs());
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_immediately(): void
    {
        $comment = $this->commentOn($this->itemWithId(1));

        $this->assertInstanceOf(ShouldBroadcastNow::class, new CommentPosted($comment));
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
