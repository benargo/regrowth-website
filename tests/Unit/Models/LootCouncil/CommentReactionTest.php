<?php

namespace Tests\Unit\Models\LootCouncil;

use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\CommentReaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class CommentReactionTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return CommentReaction::class;
    }

    #[Test]
    public function it_uses_lootcouncil_comments_reactions_table(): void
    {
        $model = new CommentReaction;

        $this->assertSame('lootcouncil_comments_reactions', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_id(): void
    {
        $model = new CommentReaction;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $comment = Comment::factory()->create();
        $user = User::factory()->create();

        $reaction = $this->create([
            'comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);

        $this->assertTableHas([
            'comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);
        $this->assertModelExists($reaction);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $reaction = $this->create();

        $this->assertNotNull($reaction->comment_id);
        $this->assertNotNull($reaction->user_id);
        $this->assertModelExists($reaction);
    }

    #[Test]
    public function factory_for_comment_state_sets_comment(): void
    {
        $comment = Comment::factory()->create();
        $reaction = $this->factory()->forComment($comment)->create();

        $this->assertSame($comment->id, $reaction->comment_id);
    }

    #[Test]
    public function factory_by_user_state_sets_user(): void
    {
        $user = User::factory()->create();
        $reaction = $this->factory()->byUser($user)->create();

        $this->assertSame($user->id, $reaction->user_id);
    }

    #[Test]
    public function it_belongs_to_a_comment(): void
    {
        $comment = Comment::factory()->create();
        $reaction = $this->create(['comment_id' => $comment->id]);

        $this->assertRelation($reaction, 'comment', BelongsTo::class);
        $this->assertTrue($reaction->comment->is($comment));
    }

    #[Test]
    public function it_belongs_to_a_user(): void
    {
        $user = User::factory()->create();
        $reaction = $this->create(['user_id' => $user->id]);

        $this->assertRelation($reaction, 'user', BelongsTo::class);
        $this->assertTrue($reaction->user->is($user));
    }

    #[Test]
    public function it_prevents_user_from_reacting_to_own_comment_via_model_validation(): void
    {
        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('You cannot react to your own comment.');

        $this->create([
            'comment_id' => $comment->id,
            'user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_allows_user_to_react_to_other_users_comments(): void
    {
        $commentAuthor = User::factory()->create();
        $reactor = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $commentAuthor->id]);

        $reaction = $this->create([
            'comment_id' => $comment->id,
            'user_id' => $reactor->id,
        ]);

        $this->assertModelExists($reaction);
        $this->assertSame($comment->id, $reaction->comment_id);
        $this->assertSame($reactor->id, $reaction->user_id);
    }

    #[Test]
    public function it_prevents_self_reaction_when_updating_user_id(): void
    {
        $commentAuthor = User::factory()->create();
        $originalReactor = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $commentAuthor->id]);

        $reaction = $this->create([
            'comment_id' => $comment->id,
            'user_id' => $originalReactor->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('You cannot react to your own comment.');

        $reaction->user_id = $commentAuthor->id;
        $reaction->save();
    }

    #[Test]
    public function it_prevents_self_reaction_when_updating_comment_id(): void
    {
        $user = User::factory()->create();
        $originalComment = Comment::factory()->create();
        $ownComment = Comment::factory()->create(['user_id' => $user->id]);

        $reaction = $this->create([
            'comment_id' => $originalComment->id,
            'user_id' => $user->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('You cannot react to your own comment.');

        $reaction->comment_id = $ownComment->id;
        $reaction->save();
    }

    #[Test]
    public function database_trigger_prevents_self_reaction_on_insert(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Database triggers only exist in MySQL.');
        }

        $user = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Users cannot react to their own comments/');

        // Bypass the model's saving event by using query builder directly
        CommentReaction::query()->getQuery()->insert([
            'comment_id' => $comment->id,
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function database_trigger_prevents_self_reaction_on_update(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Database triggers only exist in MySQL.');
        }

        $commentAuthor = User::factory()->create();
        $originalReactor = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $commentAuthor->id]);

        $reaction = $this->create([
            'comment_id' => $comment->id,
            'user_id' => $originalReactor->id,
        ]);

        $this->expectException(QueryException::class);
        $this->expectExceptionMessageMatches('/Users cannot react to their own comments/');

        // Bypass the model's saving event by using query builder directly
        CommentReaction::query()->getQuery()
            ->where('id', $reaction->id)
            ->update(['user_id' => $commentAuthor->id]);
    }

    #[Test]
    public function it_can_be_deleted(): void
    {
        $reaction = $this->create();
        $reactionId = $reaction->id;

        $reaction->delete();

        $this->assertNull(CommentReaction::find($reactionId));
    }

    #[Test]
    public function it_is_deleted_when_comment_is_force_deleted(): void
    {
        $comment = Comment::factory()->create();
        $reaction = $this->create(['comment_id' => $comment->id]);
        $reactionId = $reaction->id;

        $comment->forceDelete();

        $this->assertNull(CommentReaction::find($reactionId));
    }

    #[Test]
    public function it_is_deleted_when_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $reaction = $this->create(['user_id' => $user->id]);
        $reactionId = $reaction->id;

        $user->delete();

        $this->assertNull(CommentReaction::find($reactionId));
    }

    #[Test]
    public function multiple_users_can_react_to_same_comment(): void
    {
        $comment = Comment::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        $reaction1 = $this->create(['comment_id' => $comment->id, 'user_id' => $user1->id]);
        $reaction2 = $this->create(['comment_id' => $comment->id, 'user_id' => $user2->id]);
        $reaction3 = $this->create(['comment_id' => $comment->id, 'user_id' => $user3->id]);

        $this->assertModelExists($reaction1);
        $this->assertModelExists($reaction2);
        $this->assertModelExists($reaction3);
        $this->assertCount(3, CommentReaction::where('comment_id', $comment->id)->get());
    }

    #[Test]
    public function user_can_react_to_multiple_comments(): void
    {
        $user = User::factory()->create();
        $comment1 = Comment::factory()->create();
        $comment2 = Comment::factory()->create();
        $comment3 = Comment::factory()->create();

        $reaction1 = $this->create(['comment_id' => $comment1->id, 'user_id' => $user->id]);
        $reaction2 = $this->create(['comment_id' => $comment2->id, 'user_id' => $user->id]);
        $reaction3 = $this->create(['comment_id' => $comment3->id, 'user_id' => $user->id]);

        $this->assertModelExists($reaction1);
        $this->assertModelExists($reaction2);
        $this->assertModelExists($reaction3);
        $this->assertCount(3, CommentReaction::where('user_id', $user->id)->get());
    }
}
