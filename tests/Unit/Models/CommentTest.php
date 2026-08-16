<?php

namespace Tests\Unit\Models;

use App\Casts\AsClassName;
use App\Casts\AsKeyType;
use App\Models\Comment;
use App\Models\CommentRevision;
use App\Models\User;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

/**
 * Minimal stub used to test the polymorphic `commentable` relationship
 * without coupling this unit test to any real domain model.
 */
class CommentableStub extends Model
{
    use HasFactory;

    protected $table = 'items';

    protected static function newFactory(): ItemFactory
    {
        return ItemFactory::new();
    }

    public function comments(): MorphMany
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

#[Group('loot')]
#[Group('comments')]
class CommentTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Comment::class;
    }

    #[Test]
    public function it_uses_comments_table(): void
    {
        $model = new Comment;

        $this->assertSame('comments', $model->getTable());
    }

    #[Test]
    public function it_uses_auto_incrementing_id(): void
    {
        $model = new Comment;

        $this->assertSame('id', $model->getKeyName());
        $this->assertTrue($model->getIncrementing());
    }

    #[Test]
    public function it_has_expected_fillable_attributes(): void
    {
        $model = new Comment;

        $this->assertFillable($model, [
            'commentable_id',
            'commentable_type',
            'user_id',
            'body',
            'is_resolved',
            'deleted_by',
        ]);
    }

    #[Test]
    public function it_defaults_is_resolved_to_false(): void
    {
        $model = new Comment;

        $this->assertFalse($model->is_resolved);
    }

    #[Test]
    public function it_casts_commentable_id_with_as_key_type(): void
    {
        $model = new Comment;

        $this->assertCasts($model, [
            'commentable_id' => AsKeyType::class,
        ]);
    }

    #[Test]
    public function it_casts_commentable_type_with_as_class_name(): void
    {
        $model = new Comment;

        $this->assertCasts($model, [
            'commentable_type' => AsClassName::class,
        ]);
    }

    #[Test]
    public function it_casts_is_resolved_to_boolean(): void
    {
        $comment = $this->create(['is_resolved' => 1]);

        $this->assertIsBool($comment->is_resolved);
        $this->assertTrue($comment->is_resolved);
    }

    #[Test]
    public function it_can_be_marked_as_resolved(): void
    {
        $comment = $this->create(['is_resolved' => false]);

        $comment->update(['is_resolved' => true]);

        $this->assertTrue($comment->fresh()->is_resolved);
        $this->assertTableHas(['id' => $comment->id, 'is_resolved' => true]);
    }

    #[Test]
    public function factory_resolved_state_creates_resolved_comment(): void
    {
        $comment = $this->factory()->resolved()->create();

        $this->assertTrue($comment->is_resolved);
    }

    #[Test]
    public function it_uses_soft_deletes(): void
    {
        $this->assertContains(
            SoftDeletes::class,
            class_uses_recursive(Comment::class)
        );
    }

    #[Test]
    public function it_can_be_created_with_required_attributes(): void
    {
        $stub = CommentableStub::factory()->create();
        $user = User::factory()->create();

        $comment = $this->create([
            'commentable_id' => (string) $stub->id,
            'commentable_type' => CommentableStub::class,
            'user_id' => $user->id,
            'body' => 'This item should go to tanks first.',
        ]);

        $this->assertTableHas([
            'commentable_id' => (string) $stub->id,
            'commentable_type' => CommentableStub::class,
            'user_id' => $user->id,
            'body' => 'This item should go to tanks first.',
        ]);
        $this->assertModelExists($comment);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $comment = $this->create();

        $this->assertNotNull($comment->commentable_id);
        $this->assertNotNull($comment->commentable_type);
        $this->assertNotNull($comment->user_id);
        $this->assertNotNull($comment->body);
        $this->assertModelExists($comment);
    }

    #[Test]
    public function factory_with_body_state_sets_specific_body(): void
    {
        $comment = $this->factory()->withBody('Custom comment text')->create();

        $this->assertSame('Custom comment text', $comment->body);
    }

    #[Test]
    public function factory_short_state_creates_short_comment(): void
    {
        $comment = $this->factory()->short()->create();

        $this->assertNotEmpty($comment->body);
        $this->assertLessThan(500, strlen($comment->body));
    }

    #[Test]
    public function factory_detailed_state_creates_long_comment(): void
    {
        $comment = $this->factory()->detailed()->create();

        $this->assertNotEmpty($comment->body);
    }

    #[Test]
    public function it_belongs_to_a_commentable(): void
    {
        $stub = CommentableStub::factory()->create();
        $comment = $this->create([
            'commentable_id' => (string) $stub->id,
            'commentable_type' => CommentableStub::class,
        ]);

        $this->assertRelation($comment, 'commentable', MorphTo::class);
        $this->assertTrue($comment->commentable->is($stub));
    }

    #[Test]
    public function it_belongs_to_a_user(): void
    {
        $user = User::factory()->create();
        $comment = $this->create(['user_id' => $user->id]);

        $this->assertRelation($comment, 'user', BelongsTo::class);
        $this->assertTrue($comment->user->is($user));
    }

    #[Test]
    public function it_belongs_to_a_deleted_by_user(): void
    {
        $deleter = User::factory()->create();
        $comment = $this->create(['deleted_by' => $deleter->id]);

        $this->assertRelation($comment, 'deletedBy', BelongsTo::class);
        $this->assertTrue($comment->deletedBy->is($deleter));
    }

    #[Test]
    public function it_can_be_soft_deleted(): void
    {
        $comment = $this->create();

        $comment->delete();

        $this->assertSoftDeleted($comment);
        $this->assertNull(Comment::find($comment->id));
        $this->assertNotNull(Comment::withTrashed()->find($comment->id));
    }

    #[Test]
    public function it_can_be_restored(): void
    {
        $comment = $this->create();
        $comment->delete();

        $comment->restore();

        $this->assertNotSoftDeleted($comment);
        $this->assertNotNull(Comment::find($comment->id));
    }

    #[Test]
    public function it_can_be_force_deleted(): void
    {
        $comment = $this->create();
        $commentId = $comment->id;

        $comment->forceDelete();

        $this->assertNull(Comment::withTrashed()->find($commentId));
    }

    #[Test]
    public function it_can_track_who_deleted_the_comment(): void
    {
        $deleter = User::factory()->create();
        $comment = $this->create();

        $comment->update(['deleted_by' => $deleter->id]);
        $comment->delete();

        $trashedComment = Comment::withTrashed()->find($comment->id);

        $this->assertSame($deleter->id, $trashedComment->deleted_by);
        $this->assertTrue($trashedComment->deletedBy->is($deleter));
    }

    #[Test]
    public function it_has_many_revisions(): void
    {
        $comment = $this->make();

        $this->assertInstanceOf(HasMany::class, $comment->revisions());
        $this->assertSame('comment_id', $comment->revisions()->getForeignKeyName());
        $this->assertInstanceOf(CommentRevision::class, $comment->revisions()->getRelated());
    }

    #[Test]
    public function its_revisions_are_returned_in_creation_order(): void
    {
        $comment = $this->create();

        $first = CommentRevision::factory()->forComment($comment)->create(['body' => 'First body']);
        $second = CommentRevision::factory()->forComment($comment)->create(['body' => 'Second body']);

        $this->assertSame(
            [$first->id, $second->id],
            $comment->revisions()->orderBy('id')->pluck('id')->all()
        );
    }

    #[Test]
    public function it_has_no_parent_by_default(): void
    {
        $comment = Comment::factory()->create();

        $this->assertNull($comment->parent_id);
        $this->assertNull($comment->parent);
        $this->assertFalse($comment->isReply());
    }

    #[Test]
    public function it_belongs_to_a_parent_comment(): void
    {
        $root = Comment::factory()->create();
        $reply = Comment::factory()->replyTo($root)->create();

        $this->assertTrue($reply->isReply());
        $this->assertEquals($root->id, $reply->parent->id);
    }

    #[Test]
    public function it_has_many_replies_ordered_oldest_first(): void
    {
        $root = Comment::factory()->create();

        $older = Comment::factory()->replyTo($root)->create(['created_at' => now()->subHour()]);
        $newer = Comment::factory()->replyTo($root)->create(['created_at' => now()]);

        $replies = $root->replies()->get();

        $this->assertCount(2, $replies);
        $this->assertEquals([$older->id, $newer->id], $replies->pluck('id')->all());
    }

    #[Test]
    public function top_level_scope_excludes_replies(): void
    {
        $root = Comment::factory()->create();
        Comment::factory()->replyTo($root)->create();

        $topLevel = Comment::topLevel()->get();

        $this->assertCount(1, $topLevel);
        $this->assertEquals($root->id, $topLevel->first()->id);
    }

    #[Test]
    public function parent_id_is_not_mass_assignable(): void
    {
        $root = Comment::factory()->create();

        $comment = Comment::create([
            'commentable_id' => $root->commentable_id,
            'commentable_type' => $root->commentable_type,
            'user_id' => $root->user_id,
            'body' => 'Mass assignment attempt.',
            'parent_id' => $root->id,
        ]);

        $this->assertNull($comment->fresh()->parent_id);
    }

    #[Test]
    public function deleting_a_root_leaves_its_replies_in_place(): void
    {
        $root = Comment::factory()->create();
        $reply = Comment::factory()->replyTo($root)->create();

        $root->delete();

        $this->assertSoftDeleted('comments', ['id' => $root->id]);
        $this->assertDatabaseHas('comments', ['id' => $reply->id, 'deleted_at' => null]);
    }
}
