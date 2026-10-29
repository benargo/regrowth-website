<?php

namespace App\Jobs\RaidHelper;

use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Exceptions\DiscordRequestException;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildChannelsRequest;
use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use App\Jobs\Middleware\ReleaseWhenDiscordRateLimited;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\MaxExceptions;
use Saloon\Exceptions\Request\FatalRequestException;

#[MaxExceptions(3)]
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

    /**
     * Rate-limit releases keep the job alive until this deadline.
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
        return [new ReleaseWhenDiscordRateLimited];
    }

    public function handle(DiscordConnector $discord, RaidHelperConnector $raidHelper): void
    {
        try {
            $validChannels = $discord->send(new GetGuildChannelsRequest($raidHelper->serverId()))
                ->dto()
                ->whereIn('id', $this->channelIds)
                ->pluck('id');
        } catch (DiscordRequestException|FatalRequestException $e) {
            report($e);

            $validChannels = collect();
        }

        $validChannels->each(function (string $channelId): void {
            FetchEventsForChannel::dispatch($channelId, $this->startTimeFilter, $this->endTimeFilter);
        });
    }
}
