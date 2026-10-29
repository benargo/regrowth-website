<?php

namespace App\Jobs\Discord;

use App\Actions\Discord\ResolveDiscordChannel;
use App\Http\Integrations\Discord\Exceptions\DiscordRequestException;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use App\Notifications\Discord\DiscordNotification;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Support\Facades\Log;

/**
 * Sends a Discord notification to a configured channel, resolving the stored channel first.
 */
#[MaxExceptions(3)]
#[Backoff(60)]
class NotifyDiscordChannel implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ?string $channelId,
        public readonly DiscordNotification $notification,
    ) {}

    /**
     * Rate-limit releases keep the job alive until this deadline; three real errors still fail it.
     */
    public function retryUntil(): CarbonInterface
    {
        return now()->addHour();
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new ReleaseWhenDiscordRateLimited,
        ];
    }

    /**
     * Notify the channel, logging a warning and returning when the channel is unconfigured or Discord cannot return it.
     */
    public function handle(): void
    {
        if (blank($this->channelId)) {
            Log::warning('Skipped a Discord notification for an unconfigured channel.', [
                'notification' => $this->notification::class,
            ]);

            return;
        }

        try {
            ResolveDiscordChannel::run($this->channelId)->notifyNow($this->notification);
        } catch (DiscordRequestException $exception) {
            Log::warning('Skipped a Discord notification for a channel Discord could not return.', [
                'channel_id' => $this->channelId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['discord', 'discord:notify'];
    }
}
