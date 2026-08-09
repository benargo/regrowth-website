<?php

namespace Tests\Feature\Comments;

use App\Jobs\SyncCommentReplyCount;
use App\Models\Comment;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\NewLootCouncilComment;
use App\Services\Discord\Discord;
use App\Services\Discord\Enums\MessageType;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Resources\Channel as DiscordChannel;
use App\Services\Discord\Resources\Message as DiscordMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('comments')]
#[Group('discord-integration')]
class CommentReplyDiscordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->mockDiscordService();

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
            SyncCommentReplyCount::class,
            fn (SyncCommentReplyCount $job) => $job->root->id === $root->id,
        );
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
            SyncCommentReplyCount::class,
            fn (SyncCommentReplyCount $job) => $job->root->id === $root->id,
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

        Bus::assertNotDispatched(SyncCommentReplyCount::class);
    }

    // ↓ Helpers

    private function mockDiscordService(): void
    {
        $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('getChannel')
                ->andReturn(DiscordChannel::from(['id' => '123456789']));

            $mock->shouldReceive('createMessage')
                ->andReturn(DiscordMessage::from([
                    'id' => '999999999999999999',
                    'channel_id' => '123456789',
                    'timestamp' => now()->toIso8601String(),
                    'tts' => false,
                    'mention_everyone' => false,
                    'mention_roles' => [],
                    'attachments' => [],
                    'embeds' => [],
                    'pinned' => false,
                    'type' => MessageType::Default->value,
                ]));
        });
    }
}
