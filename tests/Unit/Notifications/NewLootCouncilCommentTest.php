<?php

namespace Tests\Unit\Notifications;

use App\Models\LootCouncil\Comment;
use App\Notifications\DiscordNotifiable;
use App\Notifications\NewLootCouncilComment;
use App\Services\Blizzard\ItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\Discord\DiscordChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NewLootCouncilCommentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_discord_channel(): void
    {
        $comment = Comment::factory()->create();
        $notification = new NewLootCouncilComment($comment);

        $this->assertSame([DiscordChannel::class], $notification->via(new DiscordNotifiable('lootcouncil')));
    }

    #[Test]
    public function it_resolves_item_name_via_blizzard_api(): void
    {
        $comment = Comment::factory()->create();

        $this->mock(ItemService::class, function ($mock) {
            $mock->shouldReceive('find')
                ->andReturn(['name' => 'Thunderfury']);
        });

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toDiscord(new DiscordNotifiable('lootcouncil'));

        $this->assertStringContainsString('Thunderfury', $message->embed['description']);
    }

    #[Test]
    public function it_falls_back_to_item_id_on_api_failure(): void
    {
        $comment = Comment::factory()->create();

        $this->mock(ItemService::class, function ($mock) {
            $mock->shouldReceive('find')
                ->andThrow(new \Exception('API error'));
        });

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toDiscord(new DiscordNotifiable('lootcouncil'));

        $this->assertStringContainsString("Item #{$comment->item->id}", $message->embed['description']);
    }

    #[Test]
    public function it_falls_back_when_name_is_null_in_response(): void
    {
        $comment = Comment::factory()->create();

        $this->mock(ItemService::class, function ($mock) {
            $mock->shouldReceive('find')
                ->andReturn(['name' => null]);
        });

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toDiscord(new DiscordNotifiable('lootcouncil'));

        $this->assertStringContainsString("Item #{$comment->item->id}", $message->embed['description']);
    }

    #[Test]
    public function it_builds_embed_with_correct_structure(): void
    {
        $comment = Comment::factory()->create();

        $this->mock(ItemService::class, function ($mock) {
            $mock->shouldReceive('find')->andReturn(['name' => 'Warglaive']);
        });

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toDiscord(new DiscordNotifiable('lootcouncil'));

        $this->assertSame('New comment received', $message->embed['title']);
        $this->assertSame(5814783, $message->embed['color']);
        $this->assertArrayHasKey('url', $message->embed);
        $this->assertArrayHasKey('timestamp', $message->embed);
    }

    #[Test]
    public function it_includes_user_mention_in_description(): void
    {
        $comment = Comment::factory()->create();

        $this->mock(ItemService::class, function ($mock) {
            $mock->shouldReceive('find')->andReturn(['name' => 'Warglaive']);
        });

        $notification = new NewLootCouncilComment($comment);
        $message = $notification->toDiscord(new DiscordNotifiable('lootcouncil'));

        $this->assertStringContainsString("<@{$comment->user->id}>", $message->embed['description']);
    }
}
