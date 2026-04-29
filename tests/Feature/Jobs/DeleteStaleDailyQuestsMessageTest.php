<?php

namespace Tests\Feature\Jobs;

use App\Jobs\DeleteStaleDailyQuestsMessage;
use App\Models\DiscordNotification;
use App\Notifications\DailyQuestsMessage;
use App\Services\Discord\Contracts\Resources\Message as MessageContract;
use App\Services\Discord\Discord;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DeleteStaleDailyQuestsMessageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_has_correct_retry_configuration(): void
    {
        $job = new DeleteStaleDailyQuestsMessage;

        $this->assertSame(2, $job->tries);
        $this->assertSame(30, $job->backoff);
    }

    #[Test]
    public function it_implements_should_queue(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new DeleteStaleDailyQuestsMessage);
    }

    #[Test]
    public function it_deletes_stale_notifications_from_discord_and_database(): void
    {
        $staleNotification = DiscordNotification::factory()->create([
            'type' => DailyQuestsMessage::class,
            'channel_id' => '111111111111111111',
            'message_id' => '222222222222222222',
            'created_at' => Carbon::yesterday()->setTime(4, 0, 0)->subSecond(),
        ]);

        $discord = $this->mock(Discord::class, function (MockInterface $mock) use ($staleNotification) {
            $mock->shouldReceive('deleteMessage')
                ->once()
                ->with(\Mockery::on(function (MessageContract $stub) use ($staleNotification) {
                    return $stub->id === $staleNotification->message_id
                        && $stub->channel_id === $staleNotification->channel_id;
                }));
        });

        (new DeleteStaleDailyQuestsMessage)->handle($discord);

        $this->assertSoftDeleted($staleNotification);
    }

    #[Test]
    public function it_does_not_delete_todays_notifications(): void
    {
        DiscordNotification::factory()->create([
            'type' => DailyQuestsMessage::class,
            'created_at' => Carbon::yesterday()->setTime(4, 0, 0),
        ]);

        $discord = $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('deleteMessage')->never();
        });

        (new DeleteStaleDailyQuestsMessage)->handle($discord);
    }

    #[Test]
    public function it_handles_multiple_stale_notifications(): void
    {
        $stale = DiscordNotification::factory()->count(3)->create([
            'type' => DailyQuestsMessage::class,
            'created_at' => Carbon::now()->subDays(3),
        ]);

        $discord = $this->mock(Discord::class, function (MockInterface $mock) {
            $mock->shouldReceive('deleteMessage')->times(3);
        });

        (new DeleteStaleDailyQuestsMessage)->handle($discord);

        foreach ($stale as $notification) {
            $this->assertSoftDeleted($notification);
        }
    }
}
