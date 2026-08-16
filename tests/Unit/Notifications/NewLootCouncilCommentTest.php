<?php

namespace Tests\Unit\Notifications;

use App\Models\Comment;
use App\Models\Item;
use App\Models\Raid;
use App\Notifications\NewLootCouncilComment;
use App\Services\Discord\Notifications\Driver as DiscordDriver;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Resources\Channel;
use App\Services\Discord\Resources\EmbedField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\LaravelData\Optional;
use Tests\TestCase;

#[Group('loot')]
#[Group('discord-integration')]
class NewLootCouncilCommentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_discord_driver(): void
    {
        $comment = Comment::factory()->create();
        $notification = new NewLootCouncilComment($comment);

        $this->assertContains(DiscordDriver::class, $notification->via($this->makeNotifiable()));
    }

    #[Test]
    public function it_resolves_item_name_from_model(): void
    {
        $item = Item::factory()->create(['name' => 'Thunderfury']);
        $comment = Comment::factory()->create(['commentable_id' => $item->id, 'commentable_type' => Item::class]);

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toMessage();

        $this->assertStringContainsString('Thunderfury', $message->embeds[0]->description);
    }

    #[Test]
    public function it_builds_embed_with_correct_structure(): void
    {
        $comment = Comment::factory()->create();

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toMessage();

        $this->assertSame('New comment received', $message->embeds[0]->title);
        $this->assertSame(5814783, $message->embeds[0]->color);
        $this->assertNotNull($message->embeds[0]->url);
        $this->assertNotNull($message->embeds[0]->timestamp);
    }

    // ==================== replies field ====================

    #[Test]
    public function it_omits_the_replies_field_when_reply_count_is_zero(): void
    {
        $comment = Comment::factory()->create();

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toMessage();

        $this->assertInstanceOf(Optional::class, $message->embeds[0]->fields);
    }

    #[Test]
    public function it_includes_a_replies_field_when_reply_count_is_set(): void
    {
        $comment = Comment::factory()->create();

        $notification = (new NewLootCouncilComment($comment))->withReplyCount(3);
        $message = $notification->toMessage();

        $fields = $message->embeds[0]->fields;

        $this->assertIsArray($fields);
        $this->assertCount(1, $fields);
        $this->assertInstanceOf(EmbedField::class, $fields[0]);
        $this->assertSame('Replies', $fields[0]->name);
        $this->assertSame('3', $fields[0]->value);
        $this->assertTrue($fields[0]->inline);
    }

    // ==================== url and sender ====================

    #[Test]
    public function it_generates_url_with_slug_path_segment(): void
    {
        $item = Item::factory()->create(['name' => 'Thunderfury']);
        $comment = Comment::factory()->create(['commentable_id' => $item->id, 'commentable_type' => Item::class]);

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toMessage();

        $url = $message->embeds[0]->url;

        $this->assertStringContainsString('/thunderfury', $url);
        $this->assertStringNotContainsString('name=', $url);
    }

    #[Test]
    public function it_includes_user_mention_in_description(): void
    {
        $comment = Comment::factory()->create();

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toMessage();

        $this->assertStringContainsString("<@{$comment->user->id}>", $message->embeds[0]->description);
    }

    #[Test]
    public function it_returns_the_comment_author_as_sender(): void
    {
        $comment = Comment::factory()->create();
        $notification = new NewLootCouncilComment($comment);

        $this->assertTrue($notification->sender()->is($comment->user));
    }

    // ==================== database payload and queue serialization ====================

    #[Test]
    public function it_returns_correct_database_payload(): void
    {
        $comment = Comment::factory()->create();
        $notifiable = $this->makeNotifiable();

        $notification = new NewLootCouncilComment($comment);
        $data = $notification->toDatabase($notifiable);

        $this->assertSame(NewLootCouncilComment::class, $data['type']);
        $this->assertSame('123456789', $data['channel_id']);
        $this->assertSame($comment->user->id, $data['created_by_user_id']);
        $this->assertArrayHasKey('embeds', $data['payload']);
    }

    #[Test]
    public function it_stores_the_related_comment_as_an_id_reference_not_a_full_model(): void
    {
        $comment = Comment::factory()->create();

        $related = (new NewLootCouncilComment($comment))->mapRelatedModels();

        $this->assertSame([
            ['model_id' => $comment->getKey(), 'model_type' => Comment::class],
        ], $related);
    }

    #[Test]
    public function it_produces_a_json_encodable_queue_payload(): void
    {
        $comment = Comment::factory()->create();

        $serialized = serialize(new NewLootCouncilComment($comment));

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
        $notification = new NewLootCouncilComment($comment);

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

        $notification = new NewLootCouncilComment($comment);

        $this->assertFalse($notification->shouldSend($this->makeNotifiable(), 'discord'));
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

        (new NewLootCouncilComment($comment))->toMessage();
    }

    // ==================== helpers ====================

    private function makeNotifiable(): NotifiableChannel
    {
        return new NotifiableChannel(Channel::from(['id' => '123456789']));
    }
}
