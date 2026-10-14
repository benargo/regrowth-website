<?php

namespace Tests\Feature\Actions\Comments;

use App\Actions\Comments\ResolveCommentThreadRoot;
use App\Models\Comment;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

#[Group('comments')]
class ResolveCommentThreadRootTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_null_when_no_parent_is_given(): void
    {
        $this->assertNull(ResolveCommentThreadRoot::run(null, Item::factory()->create()));
    }

    #[Test]
    #[Group('happy-path')]
    public function it_returns_the_parent_when_the_parent_is_a_root(): void
    {
        $root = Comment::factory()->create();

        $resolved = ResolveCommentThreadRoot::run($root->id, $root->commentable);

        $this->assertTrue($resolved->is($root));
    }

    #[Test]
    #[Group('happy-path')]
    public function it_returns_the_parents_root_when_the_parent_is_a_reply(): void
    {
        $root = Comment::factory()->create();
        $reply = Comment::factory()->replyTo($root)->create();

        $resolved = ResolveCommentThreadRoot::run($reply->id, $root->commentable);

        $this->assertTrue($resolved->is($root));
    }

    #[Test]
    public function it_aborts_when_the_parent_is_soft_deleted(): void
    {
        $root = Comment::factory()->create();
        $root->delete();

        $this->expectException(NotFoundHttpException::class);

        ResolveCommentThreadRoot::run($root->id, $root->commentable);
    }

    #[Test]
    public function it_aborts_when_the_parent_belongs_to_a_different_commentable(): void
    {
        $root = Comment::factory()->create();

        $this->expectException(NotFoundHttpException::class);

        ResolveCommentThreadRoot::run($root->id, Item::factory()->create());
    }

    #[Test]
    public function it_aborts_when_the_parents_root_is_soft_deleted(): void
    {
        $root = Comment::factory()->create();
        $reply = Comment::factory()->replyTo($root)->create();
        $commentable = $root->commentable;
        $root->delete();

        $this->expectException(NotFoundHttpException::class);

        ResolveCommentThreadRoot::run($reply->id, $commentable);
    }
}
