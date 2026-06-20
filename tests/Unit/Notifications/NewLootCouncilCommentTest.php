<?php

namespace Tests\Unit\Notifications;

use App\Http\Integrations\Blizzard\Requests\Item\GetItemRequest;
use App\Models\Comment;
use App\Notifications\NewLootCouncilComment;
use App\Services\Discord\Notifications\Driver as DiscordDriver;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Resources\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('loot')]
#[Group('discord-integration')]
#[Group('blizzard-integration')]
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
    public function it_resolves_item_name_via_blizzard_api(): void
    {
        $comment = Comment::factory()->create();

        $this->fakeItemResponse('Thunderfury');

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toMessage();

        $this->assertStringContainsString('Thunderfury', $message->embeds[0]->description);
    }

    #[Test]
    public function it_falls_back_to_item_id_when_item_not_found(): void
    {
        $comment = Comment::factory()->create();

        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetItemRequest::class => MockResponse::make(
                body: ['code' => 404, 'type' => 'BLZWEBAPI00000404', 'detail' => 'Not Found'],
                status: 404,
            ),
        ]);

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toMessage();

        $this->assertStringContainsString("Item #{$comment->commentable_id}", $message->embeds[0]->description);
    }

    #[Test]
    public function it_builds_embed_with_correct_structure(): void
    {
        $comment = Comment::factory()->create();

        $this->fakeItemResponse('Warglaive');

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toMessage();

        $this->assertSame('New comment received', $message->embeds[0]->title);
        $this->assertSame(5814783, $message->embeds[0]->color);
        $this->assertNotNull($message->embeds[0]->url);
        $this->assertNotNull($message->embeds[0]->timestamp);
    }

    #[Test]
    public function it_includes_user_mention_in_description(): void
    {
        $comment = Comment::factory()->create();

        $this->fakeItemResponse('Warglaive');

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

    #[Test]
    public function it_returns_correct_database_payload(): void
    {
        $comment = Comment::factory()->create();
        $notifiable = $this->makeNotifiable();

        $this->fakeItemResponse('Warglaive');

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

    private function makeNotifiable(): NotifiableChannel
    {
        return new NotifiableChannel(Channel::from(['id' => '123456789']));
    }

    private function fakeItemResponse(string $name = 'Thunderfury, Blessed Blade of the Windseeker', int $id = 19019): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600]),
            GetItemRequest::class => MockResponse::make(body: [
                'id' => $id,
                'name' => $name,
                'quality' => ['type' => 'LEGENDARY', 'name' => 'Legendary'],
                'level' => 80,
                'required_level' => 60,
                'media' => ['key' => ['href' => 'https://example.test/media/19019']],
                'item_class' => ['key' => ['href' => 'https://example.test/item-class/2'], 'name' => 'Weapon', 'id' => 2],
                'item_subclass' => ['key' => ['href' => 'https://example.test/item-subclass/2-7'], 'name' => 'One-Handed Sword', 'id' => 7],
                'inventory_type' => ['type' => 'WEAPONMAINHAND', 'name' => 'Main Hand'],
                'purchase_price' => 0,
                'sell_price' => 0,
            ], status: 200),
        ]);
    }
}
