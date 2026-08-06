<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\CommentReactionResource;
use App\Models\Comment;
use App\Models\CommentReaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('comments')]
#[Group('resource')]
class CommentReactionResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_the_expected_shape_when_user_is_loaded(): void
    {
        $author = User::factory()->create();
        $reactor = User::factory()->create(['username' => 'reactor']);
        $comment = Comment::factory()->create(['user_id' => $author->id]);
        $reaction = CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();

        $reaction->load('user');

        $array = (new CommentReactionResource($reaction))->toArray(new Request);

        $this->assertSame(
            ['id', 'comment_id', 'user', 'created_at'],
            array_keys($array)
        );
        $this->assertSame($reaction->id, $array['id']);
        $this->assertSame($comment->id, $array['comment_id']);
        $this->assertSame('reactor', $array['user']['username']);
        $this->assertEquals($reaction->created_at, $array['created_at']);
    }

    #[Test]
    public function it_omits_the_user_key_entirely_when_the_relation_is_not_loaded(): void
    {
        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $author->id]);
        $reaction = CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();

        $array = (new CommentReactionResource($reaction->fresh()))->resolve(new Request);

        $this->assertArrayNotHasKey('user', $array);
    }

    #[Test]
    public function it_does_not_query_for_the_user_when_the_relation_is_not_loaded(): void
    {
        $author = User::factory()->create();
        $reactor = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $author->id]);
        $reaction = CommentReaction::factory()->forComment($comment)->byUser($reactor)->create();

        $fresh = $reaction->fresh();

        DB::enableQueryLog();
        DB::flushQueryLog();

        (new CommentReactionResource($fresh))->resolve(new Request);

        $this->assertSame([], DB::getQueryLog(), 'Resolving the resource must not lazy-load the user relation.');

        DB::disableQueryLog();
    }
}
