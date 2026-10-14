<?php

namespace Tests\Feature\Jobs\RaidHelper;

use App\Http\Integrations\Discord\DiscordConnector;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Requests\Guilds\GetGuildChannelsRequest;
use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use App\Jobs\RaidHelper\FetchEvents;
use App\Jobs\RaidHelper\FetchEventsForChannel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Discord\MocksDiscordService;
use Tests\TestCase;

#[Group('raiding')]
#[Group('raidhelper-integration')]
#[Group('discord-integration')]
class FetchEventsTest extends TestCase
{
    use MocksDiscordService;

    private RaidHelperConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connector = new RaidHelperConnector(token: 'test-token', serverId: '111222333444555666');
        $this->app->instance(RaidHelperConnector::class, $this->connector);
    }

    #[Test]
    public function it_only_dispatches_for_channel_ids_that_belong_to_the_server(): void
    {
        Queue::fake();

        $validChannelId = '100000000000000001';
        $invalidChannelId = '999999999999999999';

        $this->fakeGuildChannels([$validChannelId]);

        (new FetchEvents([$validChannelId, $invalidChannelId]))->handle($this->discord(), $this->connector);

        Queue::assertPushed(FetchEventsForChannel::class, 1);
        Queue::assertPushed(FetchEventsForChannel::class, fn (FetchEventsForChannel $job): bool => $job->channelId === $validChannelId);

        Saloon::assertSent(fn (Request $request): bool => $request instanceof GetGuildChannelsRequest
            && $request->resolveEndpoint() === '/guilds/111222333444555666/channels');
    }

    #[Test]
    public function it_skips_all_channels_when_none_belong_to_the_server(): void
    {
        Queue::fake();

        $this->fakeGuildChannels([]);

        (new FetchEvents(['999999999999999999']))->handle($this->discord(), $this->connector);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_dispatches_fetch_events_for_channel_for_each_valid_channel(): void
    {
        Queue::fake();

        $this->fakeGuildChannels(['100000000000000001', '100000000000000002']);

        (new FetchEvents(['100000000000000001', '100000000000000002']))->handle($this->discord(), $this->connector);

        Queue::assertPushed(FetchEventsForChannel::class, 2);
    }

    #[Test]
    public function it_forwards_time_filters_to_fetch_events_for_channel(): void
    {
        Queue::fake();

        $channelId = '100000000000000001';
        $start = Carbon::parse('2024-01-01 06:00:00', 'UTC');
        $end = Carbon::parse('2024-01-08 05:59:59', 'UTC');

        $this->fakeGuildChannels([$channelId]);

        (new FetchEvents([$channelId], $start, $end))->handle($this->discord(), $this->connector);

        Queue::assertPushed(FetchEventsForChannel::class, fn (FetchEventsForChannel $job): bool => $job->channelId === $channelId
            && $job->startTimeFilter->eq($start)
            && $job->endTimeFilter->eq($end));
    }

    #[Test]
    #[Group('error-handling')]
    public function it_releases_itself_when_discord_is_rate_limited_fetching_channels(): void
    {
        Queue::fake();

        $this->fakeDiscord([GetGuildChannelsRequest::class => $this->makeDiscordRateLimitResponse(15.0, 'global')]);

        $job = new FetchEvents(['100000000000000001']);
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertReleased(15);
        Queue::assertNothingPushed();
    }

    #[Test]
    #[Group('error-handling')]
    public function it_continues_with_empty_channels_on_other_discord_errors(): void
    {
        Queue::fake();
        Exceptions::fake();

        $this->fakeDiscord([GetGuildChannelsRequest::class => $this->makeDiscordErrorResponse(403, 50001, 'Missing Access')]);

        $job = new FetchEvents(['100000000000000001']);
        $job->withFakeQueueInteractions();
        $this->runJobThroughMiddleware($job);

        $job->assertNotReleased();
        Queue::assertNothingPushed();
        Exceptions::assertReported(fn (ApiException $exception): bool => $exception->getDiscordCode() === 50001);
    }

    // ==================== helpers ====================

    private function discord(): DiscordConnector
    {
        return $this->app->make(DiscordConnector::class);
    }

    /**
     * @param  list<string>  $channelIds
     */
    private function fakeGuildChannels(array $channelIds): void
    {
        $this->fakeDiscord([GetGuildChannelsRequest::class => MockResponse::make(
            array_map(fn (string $id): array => $this->makeDiscordChannelResponse($id), $channelIds),
        )]);
    }
}
