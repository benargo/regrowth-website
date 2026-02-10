<?php

namespace Tests\Unit\Models\LootCouncil;

use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

class CommentTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return Comment::class;
    }

    #[Test]
    public function it_uses_lootcouncil_comments_table(): void
    {
        $model = new Comment;

        $this->assertSame('lootcouncil_comments', $model->getTable());
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
            'item_id',
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
        $item = Item::factory()->create();
        $user = User::factory()->create();

        $comment = $this->create([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'This item should go to tanks first.',
        ]);

        $this->assertTableHas([
            'item_id' => $item->id,
            'user_id' => $user->id,
            'body' => 'This item should go to tanks first.',
        ]);
        $this->assertModelExists($comment);
    }

    #[Test]
    public function factory_creates_valid_model(): void
    {
        $comment = $this->create();

        $this->assertNotNull($comment->item_id);
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
    public function it_belongs_to_an_item(): void
    {
        $item = Item::factory()->create();
        $comment = $this->create(['item_id' => $item->id]);

        $this->assertRelation($comment, 'item', BelongsTo::class);
        $this->assertTrue($comment->item->is($item));
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
}
