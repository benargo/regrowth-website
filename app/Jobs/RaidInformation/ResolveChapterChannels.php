<?php

namespace App\Jobs\RaidInformation;

use App\Actions\Discord\ResolveDiscordChannel;
use App\Http\Integrations\Discord\Exceptions\DiscordRequestException;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use App\Models\DiscordChannel;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Support\Facades\Log;

/**
 * Stores the Discord channels and threads mentioned in a chapter file.
 */
#[MaxExceptions(3)]
#[Backoff(60)]
class ResolveChapterChannels implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>  $channelIds
     */
    public function __construct(public readonly array $channelIds) {}

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
     * Fetch and store each channel not already stored, logging a warning and continuing when Discord rejects a request.
     */
    public function handle(): void
    {
        $storedIds = DiscordChannel::withTrashed()
            ->whereIn('id', $this->channelIds)
            ->pluck('id')
            ->all();

        $resolver = ResolveDiscordChannel::make();

        foreach (array_diff($this->channelIds, $storedIds) as $channelId) {
            try {
                $resolver->handle($channelId);
            } catch (DiscordRequestException $exception) {
                Log::warning('Skipped a chapter channel Discord could not return.', [
                    'channel_id' => $channelId,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['discord', 'discord:resolve-chapter-channels'];
    }
}
