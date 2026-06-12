<?php

namespace App\Jobs\RaidHelper;

use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use App\Services\Discord\Discord;
use App\Services\Discord\Exceptions\RateLimitedException;
use Carbon\CarbonInterface;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class FetchEvents implements ShouldQueue
{
    use Queueable;

    /**
     * @var array<int, string>
     */
    private array $channelIds = [];

    private CarbonInterface $startTimeFilter;

    private CarbonInterface $endTimeFilter;

    public function __construct(
        ?array $channelIds = null,
        ?CarbonInterface $startTimeFilter = null,
        ?CarbonInterface $endTimeFilter = null,
    ) {
        $this->channelIds = $channelIds ?? config('services.raidhelper.channel_ids', []);
        $this->startTimeFilter = $startTimeFilter ?? now()->subWeek()->setTime(6, 0, 0);
        $this->endTimeFilter = $endTimeFilter ?? now()->addWeek()->setTime(5, 59, 59);
    }

    public function handle(Discord $discord, RaidHelperConnector $raidHelper): void
    {
        try {
            $validChannels = $discord->getGuildChannels($raidHelper->serverId())->whereIn('id', $this->channelIds)->pluck('id');
        } catch (RateLimitedException $e) {
            Log::warning('FetchEvents: Discord rate limited fetching guild channels, releasing job.', [
                'endpoint' => $e->endpoint,
                'retry_after' => $e->retryAfter,
                'scope' => $e->scope,
            ]);
            $this->release($e->retryAfter);

            return;
        } catch (Exception $e) {
            Log::error("Failed to fetch channels from Discord API for server ID {$raidHelper->serverId()}. Error: {$e->getMessage()}");
            $validChannels = collect();
        }

        $validChannels->each(function (string $channelId): void {
            FetchEventsForChannel::dispatch($channelId, $this->startTimeFilter, $this->endTimeFilter);
        });
    }
}
