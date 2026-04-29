<?php

namespace Tests\Unit\Notifications;

use App\Models\LootCouncil\Comment;
use App\Notifications\NewLootCouncilComment;
use App\Services\Blizzard\BlizzardService;
use App\Services\Discord\Notifications\Driver as DiscordDriver;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Resources\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NewLootCouncilCommentTest extends TestCase
{
    use RefreshDatabase;

    private function makeNotifiable(): NotifiableChannel
    {
        return new NotifiableChannel(Channel::from(['id' => '123456789']));
    }

    #[Test]
    public function it_uses_discord_driver(): void
    {
        $comment = Comment::factory()->create();
        $notification = new NewLootCouncilComment($comment);

        $this->assertSame(DiscordDriver::class, $notification->via($this->makeNotifiable()));
    }

    #[Test]
    public function it_resolves_item_name_via_blizzard_api(): void
    {
        $comment = Comment::factory()->create();

        $this->mock(BlizzardService::class, function ($mock) {
            $mock->shouldReceive('findItem')
                ->andReturn(['name' => 'Thunderfury']);
        });

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toMessage();

        $this->assertStringContainsString('Thunderfury', $message->embeds[0]->description);
    }

    #[Test]
    public function it_falls_back_to_item_id_on_api_failure(): void
    {
        $comment = Comment::factory()->create();

        $this->mock(BlizzardService::class, function ($mock) {
            $mock->shouldReceive('findItem')
                ->andThrow(new \Exception('API error'));
        });

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toMessage();

        $this->assertStringContainsString("Item #{$comment->item->id}", $message->embeds[0]->description);
    }

    #[Test]
    public function it_falls_back_when_name_is_null_in_response(): void
    {
        $comment = Comment::factory()->create();

        $this->mock(BlizzardService::class, function ($mock) {
            $mock->shouldReceive('findItem')
                ->andReturn(['name' => null]);
        });

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toMessage();

        $this->assertStringContainsString("Item #{$comment->item->id}", $message->embeds[0]->description);
    }

    #[Test]
    public function it_builds_embed_with_correct_structure(): void
    {
        $comment = Comment::factory()->create();

        $this->mock(BlizzardService::class, function ($mock) {
            $mock->shouldReceive('findItem')->andReturn(['name' => 'Warglaive']);
        });

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

        $this->mock(BlizzardService::class, function ($mock) {
            $mock->shouldReceive('findItem')->andReturn(['name' => 'Warglaive']);
        });

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toMessage();

        $this->assertStringContainsString("<@{$comment->user->id}>", $message->embeds[0]->description);
    }

    #[Test]
    public function it_returns_null_for_updates(): void
    {
        $comment = Comment::factory()->create();
        $notification = new NewLootCouncilComment($comment);

        $this->assertNull($notification->updates());
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

        $this->mock(BlizzardService::class, function ($mock) {
            $mock->shouldReceive('findItem')->andReturn(['name' => 'Warglaive']);
        });

        $notification = new NewLootCouncilComment($comment);
        $data = $notification->toDatabase($notifiable);

        $this->assertSame(NewLootCouncilComment::class, $data['type']);
        $this->assertSame('123456789', $data['channel_id']);
        $this->assertSame($comment->user->id, $data['created_by_user_id']);
        $this->assertArrayHasKey('embeds', $data['payload']);
        $this->assertArrayHasKey('related_models', $data);
    }

    #[Test]
    public function it_returns_the_comment_as_a_relationship(): void
    {
        $comment = Comment::factory()->create();
        $notification = new NewLootCouncilComment($comment);

        $relationships = $notification->relationships();

        $this->assertArrayHasKey('comment', $relationships);
        $this->assertTrue($relationships['comment']->is($comment));
    }

    #[Test]
    public function it_includes_the_comment_entry_in_related_models_database_payload(): void
    {
        $comment = Comment::factory()->create();
        $notifiable = $this->makeNotifiable();

        $this->mock(BlizzardService::class, function ($mock) {
            $mock->shouldReceive('findItem')->andReturn(['name' => 'Warglaive']);
        });

        $notification = new NewLootCouncilComment($comment);
        $data = $notification->toDatabase($notifiable);

        $this->assertCount(1, $data['related_models']);
        $this->assertSame('comment', $data['related_models'][0]['name']);
        $this->assertSame(Comment::class, $data['related_models'][0]['model']);
        $this->assertSame($comment->id, $data['related_models'][0]['key']);
    }
}
