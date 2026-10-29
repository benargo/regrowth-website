<?php

namespace Tests\Unit\Notifications;

use App\Http\Integrations\Discord\Data\Channels\ChannelData;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use App\Models\Comment;
use App\Models\DiscordNotification;
use App\Models\Item;
use App\Models\Raid;
use App\Notifications\CommentPostedToItem;
use App\Notifications\Discord\DiscordChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('loot')]
#[Group('discord-integration')]
class CommentPostedToItemTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_discord_driver(): void
    {
        $comment = Comment::factory()->create();
        $notification = new CommentPostedToItem($comment);

        $this->assertContains(DiscordChannel::class, $notification->via($this->makeNotifiable()));
    }

    #[Test]
    public function it_resolves_item_name_from_model(): void
    {
        $item = Item::factory()->create(['name' => 'Thunderfury']);
        $comment = Comment::factory()->create(['commentable_id' => $item->id, 'commentable_type' => Item::class]);

        $notification = new CommentPostedToItem($comment);
        $message = $notification->toMessage();

        $this->assertStringContainsString('Thunderfury', $message->embeds[0]->description);
    }

    #[Test]
    public function it_builds_embed_with_correct_structure(): void
    {
        $comment = Comment::factory()->create();

        $notification = new CommentPostedToItem($comment);
        $message = $notification->toMessage();

        $this->assertSame('New comment received', $message->embeds[0]->title);
        $this->assertSame(5814783, $message->embeds[0]->color);
        $this->assertNotNull($message->embeds[0]->url);
        $this->assertNotNull($message->embeds[0]->timestamp);
    }

    // ==================== replies field ====================

    #[Test]
    public function it_omits_the_replies_field_when_the_comment_has_no_replies(): void
    {
        $comment = Comment::factory()->create();

        $notification = new CommentPostedToItem($comment);
        $message = $notification->toMessage();

        $this->assertInstanceOf(Optional::class, $message->embeds[0]->fields);
    }

    #[Test]
    public function it_reports_the_live_reply_count_in_a_replies_field(): void
    {
        $root = Comment::factory()->create();
        Comment::factory()->replyTo($root)->count(2)->create();
        Comment::factory()->replyTo($root)->create()->delete();

        $fields = CommentPostedToItem::refresh($root)->toMessage()->embeds[0]->fields;

        $this->assertCount(1, $fields);
        $this->assertSame('Replies', $fields[0]->name);
        $this->assertSame('2', $fields[0]->value);
        $this->assertTrue($fields[0]->inline);
    }

    #[Test]
    public function it_builds_the_message_for_a_soft_deleted_root(): void
    {
        $root = Comment::factory()->create(['body' => 'Original root']);
        Comment::factory()->replyTo($root)->create();
        $root->delete();

        $message = CommentPostedToItem::refresh($root)->toMessage();

        $this->assertStringContainsString('Original root', $message->embeds[0]->description);
    }

    // ==================== url and sender ====================

    #[Test]
    public function it_generates_url_with_slug_path_segment(): void
    {
        $item = Item::factory()->create(['name' => 'Thunderfury']);
        $comment = Comment::factory()->create(['commentable_id' => $item->id, 'commentable_type' => Item::class]);

        $notification = new CommentPostedToItem($comment);
        $message = $notification->toMessage();

        $url = $message->embeds[0]->url;

        $this->assertStringContainsString('/thunderfury', $url);
        $this->assertStringNotContainsString('name=', $url);
    }

    #[Test]
    public function it_includes_user_mention_in_description(): void
    {
        $comment = Comment::factory()->create();

        $notification = new CommentPostedToItem($comment);
        $message = $notification->toMessage();

        $this->assertStringContainsString("<@{$comment->user->id}>", $message->embeds[0]->description);
    }

    #[Test]
    public function it_returns_the_comment_author_as_sender(): void
    {
        $comment = Comment::factory()->create();
        $notification = new CommentPostedToItem($comment);

        $this->assertTrue($notification->sender()->is($comment->user));
    }

    // ==================== database payload and queue serialization ====================

    #[Test]
    public function it_returns_correct_database_payload(): void
    {
        $comment = Comment::factory()->create();
        $notifiable = $this->makeNotifiable();

        $notification = new CommentPostedToItem($comment);
        $data = $notification->toDatabase($notifiable);

        $this->assertSame(CommentPostedToItem::class, $data['type']);
        $this->assertSame('123456789', $data['channel_id']);
        $this->assertSame($comment->user->id, $data['created_by_user_id']);
        $this->assertArrayHasKey('embeds', $data['payload']);
    }

    #[Test]
    public function it_stores_the_related_comment_as_an_id_reference_not_a_full_model(): void
    {
        $comment = Comment::factory()->create();

        $related = (new CommentPostedToItem($comment))->mapRelatedModels();

        $this->assertSame([
            ['model_id' => $comment->getKey(), 'model_type' => Comment::class],
        ], $related);
    }

    #[Test]
    public function it_produces_a_json_encodable_queue_payload(): void
    {
        $comment = Comment::factory()->create();

        $serialized = serialize(new CommentPostedToItem($comment));

        $this->assertNotFalse(
            json_encode(['command' => $serialized], JSON_UNESCAPED_UNICODE),
            'Queue payload must be JSON-encodable: '.json_last_error_msg(),
        );
    }

    // ==================== should send ====================

    #[Test]
    public function it_should_send_when_commentable_is_an_item(): void
    {
        $comment = Comment::factory()->create();
        $notification = new CommentPostedToItem($comment);

        $this->assertTrue($notification->shouldSend($this->makeNotifiable(), 'discord'));
    }

    #[Test]
    public function it_should_not_send_when_commentable_is_not_an_item(): void
    {
        $raid = Raid::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $raid->id,
            'commentable_type' => Raid::class,
        ]);

        $notification = new CommentPostedToItem($comment);

        $this->assertFalse($notification->shouldSend($this->makeNotifiable(), 'discord'));
    }

    #[Test]
    public function a_refresh_should_not_send_when_the_thread_has_no_posted_message(): void
    {
        $root = Comment::factory()->create();

        $shouldSend = CommentPostedToItem::refresh($root)->shouldSend($this->makeNotifiable(), DiscordChannel::class);

        $this->assertFalse($shouldSend);
    }

    #[Test]
    public function a_refresh_should_send_when_the_thread_has_a_posted_message(): void
    {
        $root = Comment::factory()->create();
        $this->postedMessageFor($root);

        $shouldSend = CommentPostedToItem::refresh($root)->shouldSend($this->makeNotifiable(), DiscordChannel::class);

        $this->assertTrue($shouldSend);
    }

    #[Test]
    public function it_throws_when_commentable_is_not_an_item(): void
    {
        $raid = Raid::factory()->create();
        $comment = Comment::factory()->create([
            'commentable_id' => $raid->id,
            'commentable_type' => Raid::class,
        ]);

        $this->expectException(\LogicException::class);

        (new CommentPostedToItem($comment))->toMessage();
    }

    // ==================== existingNotification ====================

    #[Test]
    public function it_finds_the_message_posted_for_the_same_comment(): void
    {
        $comment = Comment::factory()->create();
        $posted = $this->postedMessageFor($comment);

        $found = (new CommentPostedToItem($comment))->existingNotification($this->makeNotifiable());

        $this->assertTrue($found->is($posted));
    }

    #[Test]
    public function it_ignores_a_message_posted_for_another_comment(): void
    {
        $this->postedMessageFor(Comment::factory()->create());

        $found = (new CommentPostedToItem(Comment::factory()->create()))->existingNotification($this->makeNotifiable());

        $this->assertNull($found);
    }

    // ==================== middleware and failure ====================

    #[Test]
    #[Group('contract')]
    public function it_scopes_its_overlap_lock_to_a_single_thread(): void
    {
        $first = Comment::factory()->create();
        $second = Comment::factory()->create();
        $keyFor = fn (Comment $root): string => CommentPostedToItem::refresh($root)
            ->middleware($this->makeNotifiable(), DiscordChannel::class)[0]->key;

        $this->assertNotSame($keyFor($first), $keyFor($second));
        $this->assertStringContainsString((string) $first->getKey(), $keyFor($first));
    }

    #[Test]
    #[Group('contract')]
    public function it_releases_itself_while_discord_is_rate_limited(): void
    {
        $middleware = CommentPostedToItem::refresh(Comment::factory()->create())
            ->middleware($this->makeNotifiable(), DiscordChannel::class);

        $this->assertInstanceOf(ReleaseWhenDiscordRateLimited::class, $middleware[1]);
    }

    #[Test]
    #[Group('contract')]
    public function a_new_comment_post_does_not_take_the_thread_overlap_lock(): void
    {
        $middleware = (new CommentPostedToItem(Comment::factory()->create()))
            ->middleware($this->makeNotifiable(), DiscordChannel::class);

        $this->assertContainsOnlyInstancesOf(ReleaseWhenDiscordRateLimited::class, $middleware);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_logs_the_comment_id_when_sending_fails(): void
    {
        $comment = Comment::factory()->create();
        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $context['comment_id'] === $comment->getKey());

        (new CommentPostedToItem($comment))->failed(new RuntimeException('Discord is down'));
    }

    // ==================== helpers ====================

    private function makeNotifiable(): ChannelData
    {
        return ChannelData::from(['id' => '123456789']);
    }

    private function postedMessageFor(Comment $comment): DiscordNotification
    {
        return DiscordNotification::factory()
            ->withRelatedModels([['model_type' => Comment::class, 'model_id' => $comment->getKey()]])
            ->create([
                'type' => CommentPostedToItem::class,
                'channel_id' => '123456789',
            ]);
    }
}
