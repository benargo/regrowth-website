<?php

namespace Tests\Feature\Comments;

use App\Models\Comment;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
