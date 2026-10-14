<?php

namespace Tests\Feature\Jobs;

use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Requests\Messages\DeleteMessageRequest;
use App\Jobs\DeleteStaleDailyQuestsMessage;
use App\Models\DiscordNotification;
use App\Notifications\DailyQuestsMessage;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('daily-quests')]
#[Group('discord-integration')]
class DeleteStaleDailyQuestsMessageTest extends TestCase
{
    use MocksDiscordService;
    use RefreshDatabase;

    #[Test]
    public function it_has_correct_retry_configuration(): void
    {
        $job = new DeleteStaleDailyQuestsMessage;

        $this->assertSame(2, $job->maxExceptions);
        $this->assertSame(30, $job->backoff);
        $this->assertTrue($job->retryUntil()->isFuture(), 'Rate-limit releases must not spend a retry.');
    }

    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new DeleteStaleDailyQuestsMessage);
    }

    #[Test]
    public function it_deletes_stale_notifications_from_discord_and_database(): void
    {
        $this->freezeTime();

        $staleNotification = DiscordNotification::factory()->create([
            'type' => DailyQuestsMessage::class,
            'channel_id' => '111111111111111111',
            'message_id' => '222222222222222222',
            'created_at' => Carbon::yesterday()->setTime(4, 0, 0)->subSecond(),
        ]);

        $this->fakeDiscord([DeleteMessageRequest::class => MockResponse::make('', 204)]);

        (new DeleteStaleDailyQuestsMessage)->handle($this->discord());

        $this->assertSoftDeleted($staleNotification);

        Saloon::assertSent(fn (Request $request): bool => $request instanceof DeleteMessageRequest
            && $request->resolveEndpoint() === '/channels/111111111111111111/messages/222222222222222222');
    }

    #[Test]
    public function it_does_not_delete_todays_notifications(): void
    {
        $this->freezeTime();

        $todaysNotification = DiscordNotification::factory()->create([
            'type' => DailyQuestsMessage::class,
            'created_at' => Carbon::yesterday()->setTime(4, 0, 0),
        ]);

        (new DeleteStaleDailyQuestsMessage)->handle($this->discord());

        $this->assertNotSoftDeleted($todaysNotification);
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_handles_multiple_stale_notifications(): void
    {
        $stale = DiscordNotification::factory()->count(3)->create([
            'type' => DailyQuestsMessage::class,
            'created_at' => now()->subDays(3),
        ]);

        $this->fakeDiscord([DeleteMessageRequest::class => MockResponse::make('', 204)]);

        (new DeleteStaleDailyQuestsMessage)->handle($this->discord());

        foreach ($stale as $notification) {
            $this->assertSoftDeleted($notification);
        }

        Saloon::mockClient()->assertSentCount(3, DeleteMessageRequest::class);
    }

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_when_discord_is_rate_limited(): void
    {
        $this->freezeTime();

        DiscordNotification::factory()->create([
            'type' => DailyQuestsMessage::class,
            'created_at' => Carbon::yesterday()->setTime(4, 0, 0)->subSecond(),
        ]);

        $this->fakeDiscord([DeleteMessageRequest::class => $this->makeDiscordRateLimitResponse(5.0)]);

        $job = new DeleteStaleDailyQuestsMessage;
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertReleased(5);
        $this->assertDatabaseCount('discord_notifications', 1);
    }

    // ==================== helpers ====================

    private function discord(): DiscordConnector
    {
        return $this->app->make(DiscordConnector::class);
    }
}
