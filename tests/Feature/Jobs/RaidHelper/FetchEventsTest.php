<?php

namespace Tests\Feature\Jobs\RaidHelper;

use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use App\Jobs\RaidHelper\FetchEvents;
use App\Jobs\RaidHelper\FetchEventsForChannel;
use App\Services\Discord\Discord;
use App\Services\Discord\Exceptions\RateLimitedException;
use App\Services\Discord\Resources\Channel;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FetchEventsTest extends TestCase
{
    private Discord&MockInterface $discord;

    private RaidHelperConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discord = Mockery::mock(Discord::class);
        $this->app->instance(Discord::class, $this->discord);

        $this->connector = new RaidHelperConnector(token: 'test-token', serverId: '111222333444555666');
        $this->app->instance(RaidHelperConnector::class, $this->connector);
    }

    // -------------------------------------------------------------------------
    // Channel validation
    // -------------------------------------------------------------------------

    #[Test]
    public function it_only_dispatches_for_channel_ids_that_belong_to_the_server(): void
    {
        Queue::fake();

        $validChannelId = '100000000000000001';
        $invalidChannelId = '999999999999999999';

        $this->discord->shouldReceive('getGuildChannels')
            ->with('111222333444555666')
            ->andReturn(Collection::make([Channel::from(['id' => $validChannelId])]));

        $job = new FetchEvents([$validChannelId, $invalidChannelId]);
        $job->handle($this->discord, $this->connector);

        Queue::assertPushed(FetchEventsForChannel::class, 1);
        Queue::assertPushed(FetchEventsForChannel::class, function (FetchEventsForChannel $job) use ($validChannelId) {
            return $job->channelId === $validChannelId;
        });
    }

    #[Test]
    public function it_skips_all_channels_when_none_belong_to_the_server(): void
    {
        Queue::fake();

        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([]));

        $job = new FetchEvents(['999999999999999999']);
        $job->handle($this->discord, $this->connector);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_dispatches_fetch_events_for_channel_for_each_valid_channel(): void
    {
        Queue::fake();

        $channelOneId = '100000000000000001';
        $channelTwoId = '100000000000000002';

        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([
                Channel::from(['id' => $channelOneId]),
                Channel::from(['id' => $channelTwoId]),
            ]));

        $job = new FetchEvents([$channelOneId, $channelTwoId]);
        $job->handle($this->discord, $this->connector);

        Queue::assertPushed(FetchEventsForChannel::class, 2);
    }

    #[Test]
    public function it_forwards_time_filters_to_fetch_events_for_channel(): void
    {
        Queue::fake();

        $channelId = '100000000000000001';
        $start = Carbon::parse('2024-01-01 06:00:00', 'UTC');
        $end = Carbon::parse('2024-01-08 05:59:59', 'UTC');

        $this->discord->shouldReceive('getGuildChannels')
            ->andReturn(Collection::make([Channel::from(['id' => $channelId])]));

        $job = new FetchEvents([$channelId], $start, $end);
        $job->handle($this->discord, $this->connector);

        Queue::assertPushed(FetchEventsForChannel::class, function (FetchEventsForChannel $job) use ($channelId, $start, $end) {
            return $job->channelId === $channelId
                && $job->startTimeFilter->eq($start)
                && $job->endTimeFilter->eq($end);
        });
    }

    // -------------------------------------------------------------------------
    // Rate limiting
    // -------------------------------------------------------------------------

    #[Test]
    public function it_releases_itself_when_discord_is_rate_limited_fetching_channels(): void
    {
        Queue::fake();

        $this->discord->shouldReceive('getGuildChannels')
            ->once()
            ->andThrow(new RateLimitedException('guilds/111222333444555666/channels', 15.0, 'global'));

        $job = new FetchEvents(['100000000000000001']);
        $job->withFakeQueueInteractions();
        $job->handle($this->discord, $this->connector);

        $job->assertReleased(15.0);
        Queue::assertNothingPushed();
    }

    #[Test]
    public function it_continues_with_empty_channels_on_other_discord_errors(): void
    {
        Queue::fake();

        $this->discord->shouldReceive('getGuildChannels')
            ->once()
            ->andThrow(new \RuntimeException('Connection timeout'));

        $job = new FetchEvents(['100000000000000001']);
        $job->withFakeQueueInteractions();
        $job->handle($this->discord, $this->connector);

        $job->assertNotReleased();
        Queue::assertNothingPushed();
    }
}
