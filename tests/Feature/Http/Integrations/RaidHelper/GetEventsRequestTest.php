<?php

namespace Tests\Feature\Http\Integrations\RaidHelper;

use App\Http\Integrations\RaidHelper\Data\Events\EventData;
use App\Http\Integrations\RaidHelper\Pagination\EventsPaginator;
use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use App\Http\Integrations\RaidHelper\Requests\GetEventsRequest;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('raidhelper-integration')]
class GetEventsRequestTest extends TestCase
{
    #[Test]
    public function it_sets_the_listing_headers_from_constructor_arguments(): void
    {
        Saloon::fake([
            GetEventsRequest::class => MockResponse::make(['eventsTransmitted' => 0, 'postedEvents' => []], 200),
        ]);

        $request = new GetEventsRequest(
            serverId: '111222333444555666',
            includeSignUps: true,
            channelId: '100',
            startTimeFilter: Carbon::parse('2024-01-01 06:00:00', 'UTC'),
            endTimeFilter: Carbon::parse('2024-01-08 05:59:59', 'UTC'),
        );

        $this->connector()->send($request);

        Saloon::assertSent(function (Request $request) {
            $headers = $request->headers();

            return $request->resolveEndpoint() === '/servers/111222333444555666/events'
                && $headers->get('IncludeSignUps') === 'true'
                && $headers->get('ChannelFilter') === '100'
                && $headers->get('StartTimeFilter') === Carbon::parse('2024-01-01 06:00:00', 'UTC')->unix()
                && $headers->get('EndTimeFilter') === Carbon::parse('2024-01-08 05:59:59', 'UTC')->unix();
        });
    }

    #[Test]
    public function it_paginates_across_pages_and_stops_below_the_page_size(): void
    {
        $responses = [
            MockResponse::make(['eventsTransmitted' => 1000, 'postedEvents' => [$this->listingEvent('1')]], 200),
            MockResponse::make(['eventsTransmitted' => 1, 'postedEvents' => [$this->listingEvent('2')]], 200),
        ];

        Saloon::fake($responses);

        $request = new GetEventsRequest(serverId: '111222333444555666', includeSignUps: true);

        $paginator = new EventsPaginator(connector: $this->connector(), request: $request);

        $collected = [];
        foreach ($paginator as $response) {
            $collected = array_merge($collected, $response->json('postedEvents'));
        }

        $this->assertCount(2, $collected);
        Saloon::assertSentCount(2);
    }

    #[Test]
    public function it_maps_a_single_event_payload_to_event_data(): void
    {
        Saloon::fake([
            GetEventsRequest::class => MockResponse::make($this->listingEvent('7'), 200),
        ]);

        $dto = $this->connector()->send(new GetEventsRequest(serverId: '111222333444555666'))->dto();

        $this->assertInstanceOf(EventData::class, $dto);
        $this->assertSame('7', $dto->id);
    }

    private function connector(): RaidHelperConnector
    {
        return new RaidHelperConnector(token: 'test-token', serverId: '111222333444555666');
    }

    private function listingEvent(string $id): array
    {
        return [
            'id' => $id, 'leaderId' => '1', 'leaderName' => 'L', 'channelId' => '100',
            'title' => 't', 'description' => '', 'startTime' => 1, 'endTime' => 2,
            'closingTime' => null, 'closeTime' => null, 'lastUpdated' => 1, 'color' => '0,0,0',
        ];
    }
}
