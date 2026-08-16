<?php

namespace Tests\Unit\Events\Broadcasts;

use App\Events\Broadcasts\CommentReactionChanged;
use App\Models\Comment;
use App\Models\CommentReaction;
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
class CommentReactionChangedTest extends TestCase
{
    #[Test]
    #[Group('contract')]
    public function it_broadcasts_on_the_commentables_public_channel(): void
    {
        $reaction = $this->reactionOn($this->commentOn($this->itemWithId(42)));

        $channels = (new CommentReactionChanged($reaction, 'created'))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('item.42', $channels[0]->name);
    }

    #[Test]
    #[Group('contract')]
    public function it_uses_a_plain_public_channel_not_a_private_channel(): void
    {
        $reaction = $this->reactionOn($this->commentOn($this->itemWithId(1)));

        $channels = (new CommentReactionChanged($reaction, 'created'))->broadcastOn();

        $this->assertNotInstanceOf(PrivateChannel::class, $channels[0]);
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_no_channels_when_the_commentable_does_not_implement_commentable(): void
    {
        $reaction = $this->reactionOn($this->commentOn(new User(['id' => 1])));

        $this->assertSame([], (new CommentReactionChanged($reaction, 'created'))->broadcastOn());
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_no_channels_when_its_comment_relation_is_unset(): void
    {
        $reaction = new CommentReaction;
        $reaction->setRelation('comment', null);

        $this->assertSame([], (new CommentReactionChanged($reaction, 'deleted'))->broadcastOn());
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_as_comment_reaction_changed(): void
    {
        $reaction = $this->reactionOn($this->commentOn($this->itemWithId(1)));

        $this->assertSame(
            'CommentReactionChanged',
            (new CommentReactionChanged($reaction, 'created'))->broadcastAs(),
        );
    }

    #[Test]
    #[Group('contract')]
    public function it_omits_the_reaction_from_a_deleted_payload(): void
    {
        $comment = $this->commentOn($this->itemWithId(1));
        $comment->id = 9;
        $reaction = $this->reactionOn($comment);

        $payload = (new CommentReactionChanged($reaction, 'deleted'))->broadcastWith();

        $this->assertSame(
            ['comment_id' => 9, 'action' => 'deleted'],
            $payload,
        );
    }

    #[Test]
    #[Group('contract')]
    public function it_broadcasts_immediately(): void
    {
        $reaction = $this->reactionOn($this->commentOn($this->itemWithId(1)));

        $this->assertInstanceOf(ShouldBroadcastNow::class, new CommentReactionChanged($reaction, 'created'));
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

    /**
     * Build an in-memory (unsaved) reaction attached to the given comment.
     */
    private function reactionOn(Comment $comment): CommentReaction
    {
        $reaction = new CommentReaction;
        $reaction->setRelation('comment', $comment);
        $reaction->comment_id = $comment->id;

        return $reaction;
    }
}
