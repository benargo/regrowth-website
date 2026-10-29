<?php

namespace Tests\Feature\Jobs\Discord;

use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Requests\Channels\GetChannelRequest;
use App\Jobs\Discord\NotifyDiscordChannel;
use App\Models\Comment;
use App\Models\DiscordChannel;
use App\Notifications\CommentPostedToItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('discord-integration')]
class NotifyDiscordChannelTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    #[Test]
    public function it_resolves_the_channel_and_sends_the_notification(): void
    {
        Saloon::fake([]);
        $channel = DiscordChannel::factory()->create();

        NotifyDiscordChannel::dispatchSync($channel->id, $this->makeNotification());

        Notification::assertSentTo($channel, CommentPostedToItem::class);
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_fetches_an_unstored_channel_before_notifying(): void
    {
        $this->fakeDiscordChannel('777', 'loot-council');

        NotifyDiscordChannel::dispatchSync('777', $this->makeNotification());

        $channel = DiscordChannel::findOrFail('777');
        $this->assertSame('loot-council', $channel->name);
        Notification::assertSentTo($channel, CommentPostedToItem::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_returns_quietly_when_the_channel_id_is_null(): void
    {
        Saloon::fake([]);
        Log::spy();

        NotifyDiscordChannel::dispatchSync(null, $this->makeNotification());

        Notification::assertNothingSent();
        Saloon::assertNothingSent();
        Log::shouldHaveReceived('warning')->once();
    }

    #[Test]
    #[Group('error-handling')]
    public function it_returns_quietly_when_the_channel_id_is_blank(): void
    {
        Saloon::fake([]);
        Log::spy();

        NotifyDiscordChannel::dispatchSync('', $this->makeNotification());

        Notification::assertNothingSent();
        Saloon::assertNothingSent();
        Log::shouldHaveReceived('warning')->once();
    }

    #[Test]
    #[Group('error-handling')]
    public function it_returns_quietly_on_a_discord_api_error(): void
    {
        Log::spy();
        $this->fakeDiscord([GetChannelRequest::class => $this->makeDiscordErrorResponse(403, 50001, 'Missing Access')]);

        $job = new NotifyDiscordChannel('777', $this->makeNotification());
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertNotFailed();
        Notification::assertNothingSent();
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context): bool => ($context['channel_id'] ?? null) === '777')
            ->once();
    }

    #[Test]
    #[Group('error-handling')]
    public function it_returns_quietly_when_the_channel_is_unknown(): void
    {
        Log::spy();
        $this->fakeDiscord([GetChannelRequest::class => $this->makeDiscordErrorResponse(404, 10003, 'Unknown Channel')]);

        $job = new NotifyDiscordChannel('777', $this->makeNotification());
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertNotFailed();
        Notification::assertNothingSent();
        Log::shouldHaveReceived('warning')->once();
    }

    #[Test]
    #[Group('error-handling')]
    public function it_throws_on_a_discord_server_error_so_the_job_retries(): void
    {
        $this->fakeDiscord([GetChannelRequest::class => $this->makeDiscordErrorResponse(500, 0, 'Internal Server Error')]);

        $this->expectException(ApiException::class);

        NotifyDiscordChannel::dispatchSync('777', $this->makeNotification());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_when_rate_limited(): void
    {
        $this->fakeDiscord([GetChannelRequest::class => $this->makeDiscordRateLimitResponse(30.0)]);

        $job = new NotifyDiscordChannel('777', $this->makeNotification());
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertReleased(30);
        Notification::assertNothingSent();
    }

    #[Test]
    #[Group('contract')]
    public function it_has_the_discord_notify_tags(): void
    {
        $this->assertSame(['discord', 'discord:notify'], (new NotifyDiscordChannel('777', $this->makeNotification()))->tags());
    }

    // ==================== helpers ====================

    private function makeNotification(): CommentPostedToItem
    {
        return new CommentPostedToItem(Comment::factory()->create());
    }
}
