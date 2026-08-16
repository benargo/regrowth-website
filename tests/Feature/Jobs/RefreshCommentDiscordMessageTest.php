<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RefreshCommentDiscordMessage;
use App\Models\Comment;
use App\Models\DiscordNotification;
use App\Models\DiscordNotificationRelatedModel;
use App\Notifications\NewLootCouncilComment;
use App\Services\Discord\Discord;
use App\Services\Discord\Enums\MessageType;
use App\Services\Discord\Notifications\NotifiableChannel;
use App\Services\Discord\Resources\Channel as DiscordChannel;
use App\Services\Discord\Resources\Message as DiscordMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('comments')]
#[Group('discord-integration')]
class RefreshCommentDiscordMessageTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockItemService();
        $this->mockDiscordService();
    }

    #[Test]
    #[Group('contract')]
    public function it_implements_should_queue(): void
    {
        $root = Comment::factory()->make();

        $this->assertInstanceOf(ShouldQueue::class, new RefreshCommentDiscordMessage($root));
    }

    #[Test]
    #[Group('contract')]
    public function it_scopes_its_overlap_key_to_a_single_root(): void
    {
        $first = Comment::factory()->create();
        $second = Comment::factory()->create();

        $keyFor = fn (Comment $root): string => (new RefreshCommentDiscordMessage($root))->middleware()[0]->key;

        $this->assertNotSame($keyFor($first), $keyFor($second));
        $this->assertStringContainsString((string) $first->getKey(), $keyFor($first));
    }

    #[Test]
    public function it_does_nothing_when_the_root_has_no_discord_notification(): void
    {
        Notification::fake();

        $root = Comment::factory()->create();
        Comment::factory()->replyTo($root)->create();

        (new RefreshCommentDiscordMessage($root))->handle(app(Discord::class));

        Notification::assertNothingSent();
    }

    #[Test]
    public function it_resends_the_notification_against_the_existing_record(): void
    {
        Notification::fake();

        $root = Comment::factory()->create();
        Comment::factory()->replyTo($root)->create();
        $this->notificationFor($root);

        (new RefreshCommentDiscordMessage($root))->handle(app(Discord::class));

        Notification::assertSentTo(
            new NotifiableChannel(DiscordChannel::from(['id' => '123456789'])),
            NewLootCouncilComment::class
        );
    }

    #[Test]
    public function it_reports_only_live_replies_in_the_discord_embed(): void
    {
        Notification::fake();

        $root = Comment::factory()->create();
        Comment::factory()->replyTo($root)->count(2)->create();
        $deleted = Comment::factory()->replyTo($root)->create();
        $deleted->delete();
        $this->notificationFor($root);

        (new RefreshCommentDiscordMessage($root))->handle(app(Discord::class));

        Notification::assertSentTo(
            new NotifiableChannel(DiscordChannel::from(['id' => '123456789'])),
            function (NewLootCouncilComment $notification): bool {
                $embed = $notification->toMessage()->toArray()['embeds'][0];
                $field = collect($embed['fields'] ?? [])->firstWhere('name', 'Replies');

                return $field !== null && $field['value'] === '2';
            }
        );
    }

    #[Test]
    public function it_omits_the_field_when_there_are_no_replies(): void
    {
        $root = Comment::factory()->create();

        $notification = new NewLootCouncilComment($root);
        $notification->withReplyCount(0);

        $embed = $notification->toMessage()->toArray()['embeds'][0];

        $this->assertEmpty(collect($embed['fields'] ?? [])->firstWhere('name', 'Replies'));
    }

    // ↓ Helpers

    /**
     * Create a DiscordNotification record standing in for the root's posted message.
     */
    private function notificationFor(Comment $root): DiscordNotification
    {
        $record = DiscordNotification::factory()->create([
            'type' => NewLootCouncilComment::class,
            'message_id' => '1234567890',
        ]);

        DiscordNotificationRelatedModel::create([
            'discord_notification_id' => $record->id,
            'model_type' => Comment::class,
            'model_id' => $root->id,
        ]);

        return $record;
    }

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
