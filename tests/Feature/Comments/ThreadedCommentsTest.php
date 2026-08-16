<?php

namespace Tests\Feature\Comments;

use App\Jobs\RefreshCommentDiscordMessage;
use App\Models\Comment;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('comments')]
#[Group('loot')]
class ThreadedCommentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $commentOnLootItems = Permission::firstOrCreate(['name' => 'comment-on-loot-items', 'guard_name' => 'web']);
        $deleteAnyComment = Permission::firstOrCreate(['name' => 'delete-any-comment', 'guard_name' => 'web']);

        $raiderRole = DiscordRole::factory()->raider()->create();
        $raiderRole->givePermissionTo($commentOnLootItems);

        $officerRole = DiscordRole::factory()->officer()->create();
        $officerRole->givePermissionTo($commentOnLootItems);
        $officerRole->givePermissionTo($deleteAnyComment);
    }

    #[Test]
    #[Group('happy-path')]
    public function a_raider_can_reply_to_a_root_comment(): void
    {
        $item = Item::factory()->create();
        $root = Comment::factory()->create([
            'commentable_id' => $item->id,
            'commentable_type' => Item::class,
        ]);

        $response = $this->actingAs(User::factory()->raider()->create())
            ->postJson(route('api.comments.store'), [
                'commentable_type' => Item::class,
                'commentable_id' => (string) $item->id,
                'body' => 'This is my reply.',
                'parent_id' => $root->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.parent_id', $root->id);

        $this->assertDatabaseHas('comments', [
            'id' => $response->json('data.id'),
            'parent_id' => $root->id,
        ]);
    }

    #[Test]
    #[Group('happy-path')]
    public function replying_to_a_reply_attaches_to_the_thread_root(): void
    {
        $item = Item::factory()->create();
        $root = Comment::factory()->create([
            'commentable_id' => $item->id,
            'commentable_type' => Item::class,
        ]);
        $reply = Comment::factory()->replyTo($root)->create();

        $response = $this->actingAs(User::factory()->raider()->create())
            ->postJson(route('api.comments.store'), [
                'commentable_type' => Item::class,
                'commentable_id' => (string) $item->id,
                'body' => 'A reply to a reply.',
                'parent_id' => $reply->id,
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.parent_id', $root->id);
    }

    #[Test]
    #[Group('validation')]
    public function a_reply_cannot_target_a_parent_on_a_different_item(): void
    {
        $item = Item::factory()->create();
        $otherItem = Item::factory()->create();
        $foreignRoot = Comment::factory()->create([
            'commentable_id' => $otherItem->id,
            'commentable_type' => Item::class,
        ]);

        $this->actingAs(User::factory()->raider()->create())
            ->postJson(route('api.comments.store'), [
                'commentable_type' => Item::class,
                'commentable_id' => (string) $item->id,
                'body' => 'A smuggled reply.',
                'parent_id' => $foreignRoot->id,
            ])
            ->assertNotFound();
    }

    #[Test]
    #[Group('validation')]
    public function a_reply_cannot_target_a_deleted_parent(): void
    {
        $item = Item::factory()->create();
        $root = Comment::factory()->create([
            'commentable_id' => $item->id,
            'commentable_type' => Item::class,
        ]);
        $root->delete();

        $this->actingAs(User::factory()->raider()->create())
            ->postJson(route('api.comments.store'), [
                'commentable_type' => Item::class,
                'commentable_id' => (string) $item->id,
                'body' => 'A reply to a ghost.',
                'parent_id' => $root->id,
            ])
            ->assertNotFound();
    }

    #[Test]
    public function deleting_a_root_leaves_its_replies_intact(): void
    {
        $item = Item::factory()->create();
        $root = Comment::factory()->create([
            'commentable_id' => $item->id,
            'commentable_type' => Item::class,
        ]);
        $reply = Comment::factory()->replyTo($root)->create();

        $this->actingAs(User::factory()->officer()->create())
            ->deleteJson(route('api.comments.destroy', ['comment' => $root->id]))
            ->assertNoContent();

        $this->assertSoftDeleted('comments', ['id' => $root->id]);
        $this->assertDatabaseHas('comments', ['id' => $reply->id, 'deleted_at' => null]);
    }

    #[Test]
    public function replying_under_a_trashed_root_does_not_create_a_new_root(): void
    {
        $user = User::factory()->raider()->create();
        $root = Comment::factory()->create();
        $reply = Comment::factory()->replyTo($root)->create();
        $root->delete();

        $response = $this->actingAs($user)->postJson(route('api.comments.store'), [
            'commentable_id' => (string) $root->commentable_id,
            'commentable_type' => Item::class,
            'body' => 'Reply under a tombstone',
            'parent_id' => $reply->id,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseMissing('comments', ['body' => 'Reply under a tombstone']);
    }

    #[Test]
    public function deleting_a_reply_under_a_trashed_root_refreshes_the_discord_message(): void
    {
        Queue::fake();

        $author = User::factory()->raider()->create();
        $root = Comment::factory()->create();
        $reply = Comment::factory()->replyTo($root)->create(['user_id' => $author->id]);
        $root->delete();

        $response = $this->actingAs($author)->deleteJson(
            route('api.comments.destroy', $reply)
        );

        $response->assertNoContent();
        Queue::assertPushed(
            RefreshCommentDiscordMessage::class,
            fn (RefreshCommentDiscordMessage $job): bool => $job->root->is($root),
        );
    }

    #[Test]
    public function the_reply_count_drops_after_a_reply_is_deleted(): void
    {
        $item = Item::factory()->create();
        $author = User::factory()->raider()->create();
        $root = Comment::factory()->create([
            'commentable_id' => $item->id,
            'commentable_type' => Item::class,
        ]);
        Comment::factory()->count(2)->replyTo($root)->create();
        $doomed = Comment::factory()->replyTo($root)->create(['user_id' => $author->id]);

        $this->actingAs($author)
            ->deleteJson(route('api.comments.destroy', $doomed))
            ->assertNoContent();

        $response = $this->actingAs($author)->get(route('loot.items.show', [
            'item' => $item->id,
            'slug' => $item->slug ?: "item-{$item->id}",
        ]));

        $response->assertInertia(fn (AssertableJson $page) => $page
            ->where('comments.data.0.replies_count', 2)
        );
    }
}
