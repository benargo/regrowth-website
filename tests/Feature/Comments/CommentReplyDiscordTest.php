<?php

namespace Tests\Feature\Comments;

use App\Http\Integrations\Discord\Data\Channels\ChannelStub;
use App\Http\Integrations\Discord\Requests\Messages\CreateMessageRequest;
use App\Http\Integrations\Discord\Requests\Messages\EditMessageRequest;
use App\Models\Comment;
use App\Models\DiscordNotification;
use App\Models\DiscordRole;
use App\Models\Item;
use App\Models\Permission;
use App\Models\User;
use App\Notifications\CommentPostedToItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Spatie\LaravelData\Optional;
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

        config(['services.discord.channels.lootcouncil' => '123456789']);
        $this->fakeDiscordMessageCreated();

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
            new ChannelStub('123456789'),
            CommentPostedToItem::class
        );
    }

    #[Test]
    public function a_reply_refreshes_the_root_message_with_its_reply_count(): void
    {
        $root = $this->rootOnItem();
        $this->postedMessageFor($root);

        Notification::fake();

        $this->actingAs(User::factory()->raider()->create())
            ->postJson(route('api.comments.store'), $this->replyPayload($root))
            ->assertCreated();

        Notification::assertSentTo(
            new ChannelStub('123456789'),
            fn (CommentPostedToItem $notification): bool => $notification->toMessage()->embeds[0]->fields[0]->value === '1',
        );
    }

    #[Test]
    public function a_reply_to_a_root_without_a_posted_message_sends_nothing(): void
    {
        $root = $this->rootOnItem();

        Notification::fake();

        $this->actingAs(User::factory()->raider()->create())
            ->postJson(route('api.comments.store'), $this->replyPayload($root))
            ->assertCreated();

        Notification::assertNothingSent();
    }

    #[Test]
    public function a_reply_whose_root_message_was_deleted_in_discord_posts_nothing(): void
    {
        $root = $this->rootOnItem();
        $stale = $this->postedMessageFor($root);

        $this->fakeDiscord([
            EditMessageRequest::class => $this->makeDiscordErrorResponse(404, 10008, 'Unknown Message'),
            CreateMessageRequest::class => MockResponse::make($this->makeDiscordMessageResponse('565656565656565656', '123456789')),
        ]);

        $this->actingAs(User::factory()->raider()->create())
            ->postJson(route('api.comments.store'), $this->replyPayload($root))
            ->assertCreated();

        $this->assertSoftDeleted($stale);
        Saloon::assertNotSent(CreateMessageRequest::class);
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
        Notification::assertNothingSent();
    }

    #[Test]
    public function deleting_a_reply_refreshes_its_root_message(): void
    {
        $root = $this->rootOnItem();
        $this->postedMessageFor($root);
        $author = User::factory()->raider()->create();
        $reply = Comment::factory()->replyTo($root)->create(['user_id' => $author->id]);

        Notification::fake();

        $this->actingAs($author)
            ->deleteJson(route('api.comments.destroy', ['comment' => $reply->id]))
            ->assertNoContent();

        Notification::assertSentTo(
            new ChannelStub('123456789'),
            fn (CommentPostedToItem $notification): bool => $notification->toMessage()->embeds[0]->fields instanceof Optional,
        );
    }

    #[Test]
    public function deleting_a_reply_under_a_tombstoned_root_still_refreshes_the_root_message(): void
    {
        $root = $this->rootOnItem();
        $this->postedMessageFor($root);
        $author = User::factory()->raider()->create();
        $reply = Comment::factory()->replyTo($root)->create(['user_id' => $author->id]);
        $root->delete();

        Notification::fake();

        $this->actingAs($author)
            ->deleteJson(route('api.comments.destroy', ['comment' => $reply->id]))
            ->assertNoContent();

        Notification::assertSentTo(new ChannelStub('123456789'), CommentPostedToItem::class);
    }

    #[Test]
    public function deleting_a_root_sends_nothing(): void
    {
        $author = User::factory()->raider()->create();
        $root = $this->rootOnItem(['user_id' => $author->id]);
        $this->postedMessageFor($root);

        Notification::fake();

        $this->actingAs($author)
            ->deleteJson(route('api.comments.destroy', ['comment' => $root->id]))
            ->assertNoContent();

        Notification::assertNothingSent();
    }

    // ==================== helpers ====================

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function rootOnItem(array $attributes = []): Comment
    {
        $item = Item::factory()->create();

        return Comment::factory()->create([
            'commentable_id' => $item->id,
            'commentable_type' => Item::class,
            ...$attributes,
        ]);
    }

    private function postedMessageFor(Comment $root): DiscordNotification
    {
        return DiscordNotification::factory()
            ->withRelatedModels([['model_type' => Comment::class, 'model_id' => $root->getKey()]])
            ->create([
                'type' => CommentPostedToItem::class,
                'channel_id' => '123456789',
            ]);
    }

    /**
     * @return array<string, string|int>
     */
    private function replyPayload(Comment $root): array
    {
        return [
            'commentable_type' => Item::class,
            'commentable_id' => (string) $root->commentable_id,
            'body' => 'A reply.',
            'parent_id' => $root->id,
        ];
    }
}
