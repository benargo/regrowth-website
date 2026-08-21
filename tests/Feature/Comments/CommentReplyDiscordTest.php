<?php

namespace Tests\Feature\Comments;

use App\Jobs\RefreshCommentDiscordMessage;
use App\Models\Comment;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\NewLootCouncilComment;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Resources\Channel as DiscordChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('comments')]
#[Group('discord-integration')]
class CommentReplyDiscordTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->mockDiscordChannel()->shouldReceive('createMessage')->andReturn($this->makeDiscordMessage());

        $commentOnLootItems = Permission::firstOrCreate(['name' => 'comment-on-loot-items', 'guard_name' => 'web']);
        $raiderRole = DiscordRole::factory()->raider()->create();
        $raiderRole->givePermissionTo($commentOnLootItems);
    }

    #[Test]
    public function a_root_comment_posts_a_discord_message(): void
    {
        Notification::fake();
        $item = Item::factory()->create();

        $this->actingAs(User::factory()->raider()->create())
            ->postJson(route('api.comments.store'), [
                'commentable_type' => Item::class,
                'commentable_id' => (string) $item->id,
                'body' => 'A root comment.',
            ])
            ->assertCreated();

        Notification::assertSentTo(
            new NotifiableChannel(DiscordChannel::from(['id' => '123456789'])),
            NewLootCouncilComment::class
        );
    }

    #[Test]
    public function a_reply_dispatches_a_sync_instead_of_posting(): void
    {
        $item = Item::factory()->create();
        $root = Comment::factory()->create([
            'commentable_id' => $item->id,
            'commentable_type' => Item::class,
        ]);

        Bus::fake();
        Notification::fake();

        $this->actingAs(User::factory()->raider()->create())
            ->postJson(route('api.comments.store'), [
                'commentable_type' => Item::class,
                'commentable_id' => (string) $item->id,
                'body' => 'A reply.',
                'parent_id' => $root->id,
            ])
            ->assertCreated();

        Bus::assertDispatched(
            RefreshCommentDiscordMessage::class,
            fn (RefreshCommentDiscordMessage $job) => $job->root->id === $root->id,
        );
        Notification::assertNothingSent();
    }

    #[Test]
    public function replying_under_a_reply_whose_root_is_trashed_is_rejected(): void
    {
        $item = Item::factory()->create();
        $root = Comment::factory()->create([
            'commentable_id' => $item->id,
            'commentable_type' => Item::class,
        ]);
        $reply = Comment::factory()->replyTo($root)->create();
        $root->delete();

        Bus::fake();
        Notification::fake();

        $this->actingAs(User::factory()->raider()->create())
            ->postJson(route('api.comments.store'), [
                'commentable_type' => Item::class,
                'commentable_id' => (string) $item->id,
                'body' => 'A reply under a trashed root.',
                'parent_id' => $reply->id,
            ])
            ->assertNotFound();

        $this->assertSame(2, Comment::withTrashed()->where('commentable_id', $item->id)->count());
        Bus::assertNotDispatched(RefreshCommentDiscordMessage::class);
        Notification::assertNothingSent();
    }

    #[Test]
    public function deleting_a_reply_dispatches_a_sync_for_its_root(): void
    {
        $item = Item::factory()->create();
        $root = Comment::factory()->create([
            'commentable_id' => $item->id,
            'commentable_type' => Item::class,
        ]);
        $author = User::factory()->raider()->create();
        $reply = Comment::factory()->replyTo($root)->create(['user_id' => $author->id]);

        Bus::fake();

        $this->actingAs($author)
            ->deleteJson(route('api.comments.destroy', ['comment' => $reply->id]))
            ->assertNoContent();

        Bus::assertDispatched(
            RefreshCommentDiscordMessage::class,
            fn (RefreshCommentDiscordMessage $job) => $job->root->id === $root->id,
        );
    }

    #[Test]
    public function deleting_a_reply_under_a_tombstoned_root_still_dispatches_a_sync(): void
    {
        $item = Item::factory()->create();
        $root = Comment::factory()->create([
            'commentable_id' => $item->id,
            'commentable_type' => Item::class,
        ]);
        $author = User::factory()->raider()->create();
        $reply = Comment::factory()->replyTo($root)->create(['user_id' => $author->id]);
        $root->delete();

        Bus::fake();

        $this->actingAs($author)
            ->deleteJson(route('api.comments.destroy', ['comment' => $reply->id]))
            ->assertNoContent();

        Bus::assertDispatched(
            RefreshCommentDiscordMessage::class,
            fn (RefreshCommentDiscordMessage $job) => $job->root->id === $root->id,
        );
    }

    #[Test]
    public function deleting_a_root_does_not_dispatch_a_sync(): void
    {
        $item = Item::factory()->create();
        $author = User::factory()->raider()->create();
        $root = Comment::factory()->create([
            'commentable_id' => $item->id,
            'commentable_type' => Item::class,
            'user_id' => $author->id,
        ]);

        Bus::fake();

        $this->actingAs($author)
            ->deleteJson(route('api.comments.destroy', ['comment' => $root->id]))
            ->assertNoContent();

        Bus::assertNotDispatched(RefreshCommentDiscordMessage::class);
    }
}
