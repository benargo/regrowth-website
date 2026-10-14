<?php

namespace Tests\Feature\Jobs;

use App\Http\Integrations\Discord\Data\Channels\ChannelData;
use App\Http\Integrations\Discord\Requests\Channels\GetChannelRequest;
use App\Jobs\RefreshCommentDiscordMessage;
use App\Models\Comment;
use App\Models\DiscordNotification;
use App\Models\DiscordNotificationRelatedModel;
use App\Notifications\Discord\NotifiableChannel;
use App\Notifications\NewLootCouncilComment;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('comments')]
#[Group('discord-integration')]
class RefreshCommentDiscordMessageTest extends TestCase
{
    use MocksBlizzardServices;
    use MocksDiscordService;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockGetItem();
        $this->applyBlizzardMocks();
        $this->fakeDiscordChannel();
        $this->fakeDiscordMessageCreated();
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

        $this->runJobThroughMiddleware(new RefreshCommentDiscordMessage($root));

        Notification::assertNothingSent();
    }

    #[Test]
    public function it_resends_the_notification_against_the_existing_record(): void
    {
        Notification::fake();

        $root = Comment::factory()->create();
        Comment::factory()->replyTo($root)->create();
        $this->notificationFor($root);

        $this->runJobThroughMiddleware(new RefreshCommentDiscordMessage($root));

        Notification::assertSentTo(
            new NotifiableChannel(ChannelData::from(['id' => '123456789'])),
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

        $this->runJobThroughMiddleware(new RefreshCommentDiscordMessage($root));

        Notification::assertSentTo(
            new NotifiableChannel(ChannelData::from(['id' => '123456789'])),
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

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_without_spending_a_retry_when_discord_is_rate_limited(): void
    {
        Notification::fake();

        $root = Comment::factory()->create();
        $this->notificationFor($root);
        $this->fakeDiscord([GetChannelRequest::class => $this->makeDiscordRateLimitResponse(20.0)]);

        $job = new RefreshCommentDiscordMessage($root);
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertReleased(20);
        $this->assertTrue($job->retryUntil()->isFuture());
        $this->assertSame(3, (new \ReflectionClass($job))->getAttributes(MaxExceptions::class)[0]->newInstance()->maxExceptions);
        Notification::assertNothingSent();
        Saloon::mockClient()->assertSentCount(1, GetChannelRequest::class);
    }

    // ==================== helpers ====================

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
}
