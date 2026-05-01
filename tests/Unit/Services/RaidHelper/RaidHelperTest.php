<?php

namespace Tests\Unit\Services\RaidHelper;

use App\Services\RaidHelper\Exceptions\NoEventsFoundException;
use App\Services\RaidHelper\RaidHelper;
use App\Services\RaidHelper\RaidHelperClient;
use App\Services\RaidHelper\Resources\PostedEvent;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RaidHelperTest extends TestCase
{
    private RaidHelperClient&MockInterface $client;

    private RaidHelper $raidHelper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = Mockery::mock(RaidHelperClient::class);
        $this->raidHelper = new RaidHelper($this->client, [
            'server_id' => '111222333444555666',
            'channel_ids' => ['100000000000000001', '100000000000000002'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    #[Test]
    public function it_casts_channel_ids_to_strings(): void
    {
        $raidHelper = new RaidHelper($this->client, [
            'server_id' => '111222333444555666',
            'channel_ids' => [100000000000000001, 100000000000000002],
        ]);

        $reflection = new \ReflectionClass($raidHelper);
        $property = $reflection->getProperty('channel_ids');
        $property->setAccessible(true);

        $channelIds = $property->getValue($raidHelper);

        $this->assertContainsOnlyString($channelIds);
    }

    #[Test]
    public function it_defaults_to_an_empty_server_id_when_not_configured(): void
    {
        $raidHelper = new RaidHelper($this->client, []);

        $reflection = new \ReflectionClass($raidHelper);
        $property = $reflection->getProperty('server_id');
        $property->setAccessible(true);

        $this->assertSame('', $property->getValue($raidHelper));
    }

    #[Test]
    public function it_defaults_to_empty_channel_ids_when_not_configured(): void
    {
        $raidHelper = new RaidHelper($this->client, []);

        $reflection = new \ReflectionClass($raidHelper);
        $property = $reflection->getProperty('channel_ids');
        $property->setAccessible(true);

        $this->assertSame([], $property->getValue($raidHelper));
    }

    // -------------------------------------------------------------------------
    // getEvents
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_a_length_aware_paginator_of_posted_events(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [
                ['id' => '999000000000000001'],
                ['id' => '999000000000000002'],
            ],
            'eventCountOverall' => 2,
            'eventCountTransmitted' => 2,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $result = $this->raidHelper->getEvents();

        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertCount(2, $result->items());
        $this->assertInstanceOf(PostedEvent::class, $result->items()[0]);
    }

    #[Test]
    public function it_uses_the_configured_server_id_in_the_api_path(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [['id' => '999000000000000001']],
            'eventCountOverall' => 1,
            'eventCountTransmitted' => 1,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $this->raidHelper->getEvents();
    }

    #[Test]
    public function it_sends_the_page_header_when_a_page_is_provided(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [['id' => '999000000000000001']],
            'eventCountOverall' => 1,
            'eventCountTransmitted' => 1,
            'currentPage' => 3,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::subset(['Page' => 3]))
            ->andReturn($response);

        $this->raidHelper->getEvents(page: 3);
    }

    #[Test]
    public function it_does_not_send_the_page_header_when_page_is_null(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [['id' => '999000000000000001']],
            'eventCountOverall' => 1,
            'eventCountTransmitted' => 1,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::not(Mockery::hasKey('Page')))
            ->andReturn($response);

        $this->raidHelper->getEvents(page: null);
    }

    #[Test]
    public function it_sends_the_include_sign_ups_header_when_requested(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [['id' => '999000000000000001']],
            'eventCountOverall' => 1,
            'eventCountTransmitted' => 1,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::subset(['IncludeSignUps' => 'true']))
            ->andReturn($response);

        $this->raidHelper->getEvents(includeSignUps: true);
    }

    #[Test]
    public function it_does_not_send_the_include_sign_ups_header_when_false(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [['id' => '999000000000000001']],
            'eventCountOverall' => 1,
            'eventCountTransmitted' => 1,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::not(Mockery::hasKey('IncludeSignUps')))
            ->andReturn($response);

        $this->raidHelper->getEvents(includeSignUps: false);
    }

    #[Test]
    public function it_sends_the_channel_filter_header_when_a_channel_id_is_provided(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [['id' => '999000000000000001']],
            'eventCountOverall' => 1,
            'eventCountTransmitted' => 1,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::subset(['ChannelFilter' => '100000000000000001']))
            ->andReturn($response);

        $this->raidHelper->getEvents(channelId: '100000000000000001');
    }

    #[Test]
    public function it_does_not_send_the_channel_filter_header_when_channel_id_is_null(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [['id' => '999000000000000001']],
            'eventCountOverall' => 1,
            'eventCountTransmitted' => 1,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::not(Mockery::hasKey('ChannelFilter')))
            ->andReturn($response);

        $this->raidHelper->getEvents(channelId: null);
    }

    #[Test]
    public function it_sends_the_start_time_filter_header_as_a_unix_timestamp(): void
    {
        $startTime = Carbon::parse('2024-01-15 20:00:00', 'UTC');

        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [['id' => '999000000000000001']],
            'eventCountOverall' => 1,
            'eventCountTransmitted' => 1,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::subset(['StartTimeFilter' => $startTime->unix()]))
            ->andReturn($response);

        $this->raidHelper->getEvents(startTimeFilter: $startTime);
    }

    #[Test]
    public function it_does_not_send_the_start_time_filter_header_when_not_provided(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [['id' => '999000000000000001']],
            'eventCountOverall' => 1,
            'eventCountTransmitted' => 1,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::not(Mockery::hasKey('StartTimeFilter')))
            ->andReturn($response);

        $this->raidHelper->getEvents(startTimeFilter: null);
    }

    #[Test]
    public function it_sends_the_end_time_filter_header_as_a_unix_timestamp(): void
    {
        $endTime = Carbon::parse('2024-01-15 23:00:00', 'UTC');

        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [['id' => '999000000000000001']],
            'eventCountOverall' => 1,
            'eventCountTransmitted' => 1,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::subset(['EndTimeFilter' => $endTime->unix()]))
            ->andReturn($response);

        $this->raidHelper->getEvents(endTimeFilter: $endTime);
    }

    #[Test]
    public function it_does_not_send_the_end_time_filter_header_when_not_provided(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [['id' => '999000000000000001']],
            'eventCountOverall' => 1,
            'eventCountTransmitted' => 1,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::not(Mockery::hasKey('EndTimeFilter')))
            ->andReturn($response);

        $this->raidHelper->getEvents(endTimeFilter: null);
    }

    #[Test]
    public function it_sets_the_paginator_total_from_event_count_overall(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [['id' => '999000000000000001']],
            'eventCountOverall' => 50,
            'eventCountTransmitted' => 10,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $result = $this->raidHelper->getEvents();

        $this->assertSame(50, $result->total());
    }

    #[Test]
    public function it_sets_the_paginator_per_page_from_event_count_transmitted(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [['id' => '999000000000000001']],
            'eventCountOverall' => 50,
            'eventCountTransmitted' => 10,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $result = $this->raidHelper->getEvents();

        $this->assertSame(10, $result->perPage());
    }

    #[Test]
    public function it_sets_the_paginator_current_page_from_the_response(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [],
            'eventCountOverall' => 50,
            'eventCountTransmitted' => 10,
            'currentPage' => 4,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $result = $this->raidHelper->getEvents(page: 4);

        $this->assertSame(4, $result->currentPage());
    }

    #[Test]
    public function it_falls_back_to_the_count_of_raw_events_when_response_counts_are_absent(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [
                ['id' => '999000000000000001'],
                ['id' => '999000000000000002'],
                ['id' => '999000000000000003'],
            ],
            'eventCountTransmitted' => 3,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $result = $this->raidHelper->getEvents();

        $this->assertSame(3, $result->total());
        $this->assertSame(3, $result->perPage());
    }

    #[Test]
    public function it_throws_no_events_found_exception_when_event_count_transmitted_is_zero(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [],
            'eventCountOverall' => 0,
            'eventCountTransmitted' => 0,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $this->expectException(NoEventsFoundException::class);

        $this->raidHelper->getEvents();
    }

    #[Test]
    public function it_caps_per_page_at_1000(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [['id' => '999000000000000001']],
            'eventCountOverall' => 5000,
            'eventCountTransmitted' => 2000,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $result = $this->raidHelper->getEvents();

        $this->assertSame(1000, $result->perPage());
    }
}
