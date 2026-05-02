<?php

namespace Tests\Unit\Services\RaidHelper;

use App\Services\RaidHelper\Exceptions\NoEventsFoundException;
use App\Services\RaidHelper\RaidHelper;
use App\Services\RaidHelper\RaidHelperClient;
use App\Services\RaidHelper\Resources\Comp;
use App\Services\RaidHelper\Resources\Event;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Pagination\Paginator;
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
        $property = $reflection->getProperty('channelIds');
        $property->setAccessible(true);

        $channelIds = $property->getValue($raidHelper);

        $this->assertContainsOnlyString($channelIds);
    }

    #[Test]
    public function it_defaults_to_an_empty_server_id_when_not_configured(): void
    {
        $raidHelper = new RaidHelper($this->client, []);

        $reflection = new \ReflectionClass($raidHelper);
        $property = $reflection->getProperty('serverId');
        $property->setAccessible(true);

        $this->assertSame('', $property->getValue($raidHelper));
    }

    #[Test]
    public function it_defaults_to_empty_channel_ids_when_not_configured(): void
    {
        $raidHelper = new RaidHelper($this->client, []);

        $reflection = new \ReflectionClass($raidHelper);
        $property = $reflection->getProperty('channelIds');
        $property->setAccessible(true);

        $this->assertSame([], $property->getValue($raidHelper));
    }

    // -------------------------------------------------------------------------
    // getServerId
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_the_configured_server_id(): void
    {
        $this->assertSame('111222333444555666', $this->raidHelper->getServerId());
    }

    #[Test]
    public function get_server_id_returns_empty_string_when_not_configured(): void
    {
        $raidHelper = new RaidHelper($this->client, []);

        $this->assertSame('', $raidHelper->getServerId());
    }

    // -------------------------------------------------------------------------
    // withServer
    // -------------------------------------------------------------------------

    #[Test]
    public function with_server_overrides_the_server_id(): void
    {
        $this->raidHelper->withServer('999888777666555444');

        $this->assertSame('999888777666555444', $this->raidHelper->getServerId());
    }

    #[Test]
    public function with_server_returns_the_same_instance(): void
    {
        $result = $this->raidHelper->withServer('999888777666555444');

        $this->assertSame($this->raidHelper, $result);
    }

    #[Test]
    public function with_server_affects_subsequent_api_calls(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn($this->minimalEventPayload());

        $this->client->expects('get')
            ->with('/servers/999888777666555444/events/12345')
            ->andReturn($response);

        $this->raidHelper->withServer('999888777666555444')->getEvent(12345);
    }

    // -------------------------------------------------------------------------
    // getEvent
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_an_event_for_the_given_event_id(): void
    {
        $payload = $this->minimalEventPayload();

        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn($payload);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events/12345')
            ->andReturn($response);

        $result = $this->raidHelper->getEvent(12345);

        $this->assertInstanceOf(Event::class, $result);
        $this->assertSame('999000000000000001', $result->id);
    }

    #[Test]
    public function get_event_uses_the_configured_server_id_in_the_api_path(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn($this->minimalEventPayload());

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events/12345')
            ->andReturn($response);

        $this->raidHelper->getEvent(12345);
    }

    #[Test]
    public function get_event_maps_the_response_to_an_event_value_object(): void
    {
        $payload = $this->minimalEventPayload(['title' => 'Molten Core']);

        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn($payload);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events/12345')
            ->andReturn($response);

        $result = $this->raidHelper->getEvent(12345);

        $this->assertSame('Molten Core', $result->title);
    }

    // -------------------------------------------------------------------------
    // getComp
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_a_comp_for_the_given_event_id(): void
    {
        $payload = $this->minimalCompPayload();

        $response = Mockery::mock(Response::class);
        $response->allows('status')->withNoArgs()->andReturn(200);
        $response->allows('body')->withNoArgs()->andReturn('');
        $response->expects('json')->withNoArgs()->andReturn($payload);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/comps/12345')
            ->andReturn($response);

        $result = $this->raidHelper->getComp(12345);

        $this->assertInstanceOf(Comp::class, $result);
        $this->assertSame('999000000000000001', $result->id);
    }

    #[Test]
    public function get_comp_uses_the_configured_server_id_in_the_api_path(): void
    {
        $response = Mockery::mock(Response::class);
        $response->allows('status')->withNoArgs()->andReturn(200);
        $response->allows('body')->withNoArgs()->andReturn('');
        $response->expects('json')->withNoArgs()->andReturn($this->minimalCompPayload());

        $this->client->expects('get')
            ->with('/servers/111222333444555666/comps/12345')
            ->andReturn($response);

        $this->raidHelper->getComp(12345);
    }

    #[Test]
    public function get_comp_maps_the_response_to_a_comp_value_object(): void
    {
        $payload = $this->minimalCompPayload(['title' => 'Molten Core Comp']);

        $response = Mockery::mock(Response::class);
        $response->allows('status')->withNoArgs()->andReturn(200);
        $response->allows('body')->withNoArgs()->andReturn('');
        $response->expects('json')->withNoArgs()->andReturn($payload);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/comps/12345')
            ->andReturn($response);

        $result = $this->raidHelper->getComp(12345);

        $this->assertSame('Molten Core Comp', $result->title);
    }

    #[Test]
    public function get_comp_returns_null_when_the_api_returns_a_404(): void
    {
        $response = Mockery::mock(Response::class);
        $response->allows('status')->withNoArgs()->andReturn(404);
        $response->allows('body')->withNoArgs()->andReturn('');

        $this->client->expects('get')
            ->with('/servers/111222333444555666/comps/12345')
            ->andReturn($response);

        $result = $this->raidHelper->getComp(12345);

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // getEvents
    // -------------------------------------------------------------------------

    #[Test]
    public function it_returns_a_paginator_of_posted_events(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [
                $this->minimalListingEventPayload(['id' => '999000000000000001']),
                $this->minimalListingEventPayload(['id' => '999000000000000002']),
            ],
            'eventsOverall' => 2,
            'eventsTransmitted' => 2,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $result = $this->raidHelper->getEvents();

        $this->assertInstanceOf(Paginator::class, $result);
        $this->assertCount(2, $result->items());
        $this->assertInstanceOf(Event::class, $result->items()[0]);
    }

    #[Test]
    public function it_uses_the_configured_server_id_in_the_api_path(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [$this->minimalListingEventPayload()],
            'eventsOverall' => 1,
            'eventsTransmitted' => 1,
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
            'postedEvents' => [$this->minimalListingEventPayload()],
            'eventsOverall' => 1,
            'eventsTransmitted' => 1,
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
            'postedEvents' => [$this->minimalListingEventPayload()],
            'eventsOverall' => 1,
            'eventsTransmitted' => 1,
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
            'postedEvents' => [$this->minimalListingEventPayload()],
            'eventsOverall' => 1,
            'eventsTransmitted' => 1,
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
            'postedEvents' => [$this->minimalListingEventPayload()],
            'eventsOverall' => 1,
            'eventsTransmitted' => 1,
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
            'postedEvents' => [$this->minimalListingEventPayload()],
            'eventsOverall' => 1,
            'eventsTransmitted' => 1,
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
            'postedEvents' => [$this->minimalListingEventPayload()],
            'eventsOverall' => 1,
            'eventsTransmitted' => 1,
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
            'postedEvents' => [$this->minimalListingEventPayload()],
            'eventsOverall' => 1,
            'eventsTransmitted' => 1,
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
            'postedEvents' => [$this->minimalListingEventPayload()],
            'eventsOverall' => 1,
            'eventsTransmitted' => 1,
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
            'postedEvents' => [$this->minimalListingEventPayload()],
            'eventsOverall' => 1,
            'eventsTransmitted' => 1,
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
            'postedEvents' => [$this->minimalListingEventPayload()],
            'eventsOverall' => 1,
            'eventsTransmitted' => 1,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::not(Mockery::hasKey('EndTimeFilter')))
            ->andReturn($response);

        $this->raidHelper->getEvents(endTimeFilter: null);
    }

    #[Test]
    public function it_sets_the_paginator_current_page_from_the_response(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [],
            'eventsOverall' => 50,
            'eventsTransmitted' => 10,
            'currentPage' => 4,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $result = $this->raidHelper->getEvents(page: 4);

        $this->assertSame(4, $result->currentPage());
    }

    #[Test]
    public function it_has_no_more_pages_when_events_transmitted_is_below_1000(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [$this->minimalListingEventPayload()],
            'eventsOverall' => 999,
            'eventsTransmitted' => 999,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $result = $this->raidHelper->getEvents();

        $this->assertFalse($result->hasMorePages());
    }

    #[Test]
    public function it_has_more_pages_when_events_transmitted_reaches_1000(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => array_fill(0, 1000, $this->minimalListingEventPayload()),
            'eventsOverall' => 2000,
            'eventsTransmitted' => 1000,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $result = $this->raidHelper->getEvents();

        $this->assertTrue($result->hasMorePages());
    }

    #[Test]
    public function it_has_more_pages_when_channel_filter_is_active_and_events_transmitted_reaches_1000(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => array_fill(0, 1000, $this->minimalListingEventPayload()),
            'eventsOverall' => 26,
            'eventsTransmitted' => 1000,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $result = $this->raidHelper->getEvents(channelId: '100000000000000001');

        $this->assertTrue($result->hasMorePages());
    }

    #[Test]
    public function it_has_no_more_pages_when_channel_filter_is_active_and_events_transmitted_is_below_1000(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [$this->minimalListingEventPayload()],
            'eventsOverall' => 26,
            'eventsTransmitted' => 1,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $result = $this->raidHelper->getEvents(channelId: '100000000000000001');

        $this->assertFalse($result->hasMorePages());
    }

    #[Test]
    public function it_throws_no_events_found_exception_when_events_transmitted_is_zero(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [],
            'eventsOverall' => 0,
            'eventsTransmitted' => 0,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $this->expectException(NoEventsFoundException::class);

        $this->raidHelper->getEvents();
    }

    #[Test]
    public function it_always_uses_1000_as_per_page(): void
    {
        $response = Mockery::mock(Response::class);
        $response->expects('json')->withNoArgs()->andReturn([
            'postedEvents' => [$this->minimalListingEventPayload()],
            'eventsOverall' => 5000,
            'eventsTransmitted' => 500,
            'currentPage' => 1,
        ]);

        $this->client->expects('get')
            ->with('/servers/111222333444555666/events', Mockery::any())
            ->andReturn($response);

        $result = $this->raidHelper->getEvents();

        $this->assertSame(1000, $result->perPage());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Minimal payload for a single event as returned by the detail endpoint.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalEventPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => '999000000000000001',
            'serverId' => '111222333444555666',
            'leaderId' => '200000000000000001',
            'leaderName' => 'Raid Leader',
            'channelId' => '100000000000000001',
            'channelName' => 'raid-signups',
            'channelType' => 'GUILD_TEXT',
            'templateId' => 'wowclassic',
            'templateEmoteId' => '0',
            'title' => 'Weekly Raid',
            'description' => '',
            'startTime' => 1700000000,
            'endTime' => 1700007200,
            'closingTime' => 1699999800,
            'date' => '2023-11-14',
            'time' => '20:00',
            'advancedSettings' => [],
            'classes' => [],
            'roles' => [],
            'signUps' => [],
            'lastUpdated' => 1699999000,
            'color' => '0,0,0',
        ], $overrides);
    }

    /**
     * Minimal payload for a comp as returned by the comp endpoint.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalCompPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => '999000000000000001',
            'title' => 'Weekly Comp',
            'editPermissions' => 'managers',
            'showRoles' => true,
            'showClasses' => true,
            'groupCount' => 0,
            'slotCount' => 0,
            'groups' => [],
            'dividers' => [],
            'classes' => [],
            'slots' => [],
        ], $overrides);
    }

    /**
     * Minimal payload for an event as returned by the listing endpoint.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalListingEventPayload(array $overrides = []): array
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
