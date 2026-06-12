<?php

namespace Tests\Feature\Jobs\RaidHelper;

use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use App\Http\Integrations\RaidHelper\Requests\GetEventsRequest;
use App\Jobs\RaidHelper\FetchEventsForChannel;
use App\Jobs\RaidHelper\SyncEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Saloon\RateLimitPlugin\Exceptions\RateLimitReachedException;
use Saloon\RateLimitPlugin\Limit;
use Tests\TestCase;

class FetchEventsForChannelTest extends TestCase
{
    private RaidHelperConnector $connector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connector = new RaidHelperConnector(token: 'test-token', serverId: '111222333444555666');
        $this->app->instance(RaidHelperConnector::class, $this->connector);
    }

    #[Test]
    public function it_dispatches_fetch_event_for_each_event_on_the_page(): void
    {
        Queue::fake();

        Saloon::fake([
            GetEventsRequest::class => MockResponse::make([
                'eventsTransmitted' => 2,
                'postedEvents' => [
                    $this->minimalEventPayload(['id' => '999000000000000001']),
                    $this->minimalEventPayload(['id' => '999000000000000002']),
                ],
            ], 200),
        ]);

        $job = new FetchEventsForChannel(
            channelId: '100000000000000001',
            startTimeFilter: Carbon::parse('2024-01-01 06:00:00', 'UTC'),
            endTimeFilter: Carbon::parse('2024-01-08 05:59:59', 'UTC'),
        );
        $job->handle($this->connector);

        Queue::assertPushed(SyncEvent::class, 2);
    }

    #[Test]
    public function it_collects_events_across_multiple_pages(): void
    {
        Queue::fake();

        Saloon::fake([
            MockResponse::make(['eventsTransmitted' => 1000, 'postedEvents' => [$this->minimalEventPayload(['id' => '999000000000000001'])]], 200),
            MockResponse::make(['eventsTransmitted' => 1, 'postedEvents' => [$this->minimalEventPayload(['id' => '999000000000000002'])]], 200),
        ]);

        $job = new FetchEventsForChannel(
            channelId: '100000000000000001',
            startTimeFilter: Carbon::parse('2024-01-01 06:00:00', 'UTC'),
            endTimeFilter: Carbon::parse('2024-01-08 05:59:59', 'UTC'),
        );
        $job->handle($this->connector);

        Queue::assertPushed(SyncEvent::class, 2);
    }

    #[Test]
    public function it_passes_the_time_filters_to_get_events(): void
    {
        $channelId = '100000000000000001';
        $start = Carbon::parse('2024-01-01 06:00:00', 'UTC');
        $end = Carbon::parse('2024-01-08 05:59:59', 'UTC');

        Saloon::fake([
            GetEventsRequest::class => MockResponse::make(['eventsTransmitted' => 0, 'postedEvents' => []], 200),
        ]);

        $job = new FetchEventsForChannel(channelId: $channelId, startTimeFilter: $start, endTimeFilter: $end);
        $job->handle($this->connector);

        Saloon::assertSent(function (Request $request) use ($channelId, $start, $end) {
            $headers = $request->headers();

            return $headers->get('ChannelFilter') === $channelId
                && $headers->get('StartTimeFilter') === $start->unix()
                && $headers->get('EndTimeFilter') === $end->unix();
        });
    }

    #[Test]
    public function it_requests_sign_ups_when_fetching_events(): void
    {
        Saloon::fake([
            GetEventsRequest::class => MockResponse::make(['eventsTransmitted' => 0, 'postedEvents' => []], 200),
        ]);

        $job = new FetchEventsForChannel(
            channelId: '100000000000000001',
            startTimeFilter: Carbon::parse('2024-01-01 06:00:00', 'UTC'),
            endTimeFilter: Carbon::parse('2024-01-08 05:59:59', 'UTC'),
        );
        $job->handle($this->connector);

        Saloon::assertSent(function (Request $request) {
            return $request->headers()->get('IncludeSignUps') === 'true';
        });
    }

    #[Test]
    public function it_releases_itself_and_logs_the_tier_when_the_rate_limit_is_reached(): void
    {
        Queue::fake();

        $limit = Limit::allow(2)->everySeconds(5)->name('2_per_5s');
        $limit->exceeded(releaseInSeconds: 5);

        $connector = Mockery::mock(RaidHelperConnector::class)->makePartial();
        $connector->shouldReceive('serverId')->andReturn('111222333444555666');
        $connector->shouldReceive('send')->andThrow(new RateLimitReachedException($limit));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'rate limit reached')
                    && $context['limit'] === 'saloon_rate_limiter:2_per_5s';
            });

        $job = Mockery::mock(FetchEventsForChannel::class, [
            '100000000000000001',
            Carbon::parse('2024-01-01 06:00:00', 'UTC'),
            Carbon::parse('2024-01-08 05:59:59', 'UTC'),
        ])->makePartial();
        $job->shouldReceive('release')->once()->with(Mockery::type('int'));

        $job->handle($connector);

        Queue::assertNotPushed(SyncEvent::class);
    }

    #[Test]
    public function it_does_nothing_when_no_events_are_found(): void
    {
        Queue::fake();

        Saloon::fake([
            GetEventsRequest::class => MockResponse::make(['eventsTransmitted' => 0, 'postedEvents' => []], 200),
        ]);

        $job = new FetchEventsForChannel(
            channelId: '100000000000000001',
            startTimeFilter: Carbon::parse('2024-01-01 06:00:00', 'UTC'),
            endTimeFilter: Carbon::parse('2024-01-08 05:59:59', 'UTC'),
        );
        $job->handle($this->connector);

        Queue::assertNothingPushed();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalEventPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => '999000000000000001',
            'channelId' => '100000000000000001',
            'leaderId' => '200000000000000001',
            'leaderName' => 'Raid Leader',
            'title' => 'Weekly Raid',
            'description' => '',
            'startTime' => 1700000000,
            'endTime' => 1700007200,
            'closingTime' => 1699999800,
            'lastUpdated' => 1699999000,
            'color' => '0,0,0',
        ], $overrides);
    }
}
