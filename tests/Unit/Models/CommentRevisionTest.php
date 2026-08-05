<?php

namespace Tests\Unit\Models;

use App\Models\Comment;
use App\Models\CommentRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\ModelTestCase;

#[Group('comments')]
class CommentRevisionTest extends ModelTestCase
{
    protected function modelClass(): string
    {
        return CommentRevision::class;
    }

    #[Test]
    public function it_uses_the_comment_revisions_table(): void
    {
        $this->assertSame('comment_revisions', $this->make()->getTable());
    }

    #[Test]
    public function it_has_the_expected_fillable_attributes(): void
    {
        $this->assertFillable($this->make(), ['comment_id', 'body', 'edited_by']);
    }

    #[Test]
    public function it_does_not_maintain_updated_at(): void
    {
        $this->assertNull(CommentRevision::UPDATED_AT);
        $this->assertFalse(
            in_array('updated_at', Schema::getColumnListing('comment_revisions'), true)
        );
    }

    #[Test]
    public function it_belongs_to_a_comment(): void
    {
        $revision = $this->make();

        $this->assertInstanceOf(BelongsTo::class, $revision->comment());
        $this->assertSame('comment_id', $revision->comment()->getForeignKeyName());
        $this->assertInstanceOf(Comment::class, $revision->comment()->getRelated());
    }

    #[Test]
    public function it_belongs_to_the_user_who_made_the_edit(): void
    {
        $revision = $this->make();

        $this->assertInstanceOf(BelongsTo::class, $revision->editedBy());
        $this->assertSame('edited_by', $revision->editedBy()->getForeignKeyName());
        $this->assertInstanceOf(User::class, $revision->editedBy()->getRelated());
    }

    #[Test]
    public function edited_by_stores_the_users_string_key(): void
    {
        $editor = User::factory()->create();
        $comment = Comment::factory()->create();

        $revision = CommentRevision::create([
            'comment_id' => $comment->id,
            'body' => 'The body before the edit',
            'edited_by' => $editor->id,
        ]);

        $this->assertIsString($revision->fresh()->edited_by);
        $this->assertSame($editor->id, $revision->fresh()->edited_by);
        $this->assertInstanceOf(User::class, $revision->editedBy()->first());
    }

    #[Test]
    public function the_editor_may_differ_from_the_comment_author(): void
    {
        $author = User::factory()->create();
        $officer = User::factory()->create();
        $comment = Comment::factory()->create(['user_id' => $author->id]);

        $revision = CommentRevision::create([
            'comment_id' => $comment->id,
            'body' => 'Body written by the author',
            'edited_by' => $officer->id,
        ]);

        $this->assertNotSame($comment->user_id, $revision->edited_by);
        $this->assertSame($officer->id, $revision->edited_by);
    }
}
