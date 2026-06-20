<?php

namespace Tests\Feature\Api\Loot;

use App\Models\Boss;
use App\Models\Comment;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class ResolveCommentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function createItem(): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        return Item::factory()->create(['raid_id' => $raid->id, 'boss_id' => $boss->id]);
    }

    // ==========================================
    // Authentication tests
    // ==========================================

    #[Group('authorization')]
    #[Test]
    public function resolve_requires_authentication(): void
    {
        $comment = Comment::factory()->create();

        $response = $this->postJson(route('api.loot.comments.resolve', $comment));

        $response->assertUnauthorized();
    }

    #[Group('authorization')]
    #[Test]
    public function resolve_forbidden_without_mark_as_resolved_permission(): void
    {
        $user = User::factory()->raider()->create();
        $comment = Comment::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('api.loot.comments.resolve', $comment));

        $response->assertForbidden();
    }

    // ==========================================
    // Resolve tests
    // ==========================================

    #[Test]
    public function resolve_creates_new_resolved_comment_and_soft_deletes_original(): void
    {
        $user = User::factory()->withPermissions('mark-comment-as-resolved')->create();
        $item = $this->createItem();
        $author = User::factory()->raider()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $author->id,
            'body' => 'Comment to resolve',
            'is_resolved' => false,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.loot.comments.resolve', $comment));

        $response->assertOk();

        $this->assertSoftDeleted('comments', [
            'id' => $comment->id,
            'body' => 'Comment to resolve',
        ]);

        $this->assertDatabaseHas('comments', [
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $author->id,
            'body' => 'Comment to resolve',
            'is_resolved' => true,
        ]);
    }

    #[Test]
    public function resolve_preserves_original_created_at(): void
    {
        $user = User::factory()->withPermissions('mark-comment-as-resolved')->create();
        $item = $this->createItem();
        $author = User::factory()->raider()->create();
        $originalTime = now()->subDays(3);
        $comment = Comment::factory()->create([
            'commentable_id' => (string) $item->id,
            'commentable_type' => Item::class,
            'user_id' => $author->id,
            'created_at' => $originalTime,
        ]);

        $this->actingAs($user)
            ->postJson(route('api.loot.comments.resolve', $comment));

        $newComment = Comment::where('commentable_id', (string) $item->id)->where('commentable_type', Item::class)
            ->where('is_resolved', true)
            ->whereNull('deleted_at')
            ->first();

        $this->assertEquals(
            $originalTime->format('Y-m-d H:i:s'),
            $newComment->created_at->format('Y-m-d H:i:s')
        );
    }

    #[Test]
    public function resolve_soft_deletes_original_with_null_deleted_by(): void
    {
        $user = User::factory()->withPermissions('mark-comment-as-resolved')->create();
        $comment = Comment::factory()->create(['is_resolved' => false]);

        $this->actingAs($user)
            ->postJson(route('api.loot.comments.resolve', $comment))
            ->assertOk();

        $this->assertSoftDeleted('comments', [
            'id' => $comment->id,
            'deleted_by' => null,
        ]);
    }

    #[Test]
    public function resolve_rolls_back_when_original_delete_fails(): void
    {
        $user = User::factory()->withPermissions('mark-comment-as-resolved')->create();
        $comment = Comment::factory()->create(['is_resolved' => false]);

        Comment::deleting(function () {
            throw new \RuntimeException('Simulated delete failure');
        });

        try {
            $this->actingAs($user)
                ->postJson(route('api.loot.comments.resolve', $comment));
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'is_resolved' => false,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseMissing('comments', [
            'commentable_id' => $comment->commentable_id,
            'is_resolved' => true,
        ]);
    }

    #[Test]
    public function resolve_returns_new_comment_in_response(): void
    {
        $user = User::factory()->withPermissions('mark-comment-as-resolved')->create();
        $comment = Comment::factory()->create([
            'body' => 'Resolve me',
            'is_resolved' => false,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('api.loot.comments.resolve', $comment));

        $response->assertOk();
        $response->assertJsonFragment([
            'body' => 'Resolve me',
            'is_resolved' => true,
        ]);
    }
}
