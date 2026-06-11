<?php

namespace Tests\Unit\Http\Integrations\RaidHelper\Pagination;

use App\Http\Integrations\RaidHelper\Pagination\EventsPaginator;
use App\Http\Integrations\RaidHelper\RaidHelperConnector;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;
use Saloon\PaginationPlugin\Contracts\Paginatable;
use Tests\TestCase;

class EventsPaginatorTest extends TestCase
{
    #[Test]
    public function it_iterates_through_multiple_pages(): void
    {
        Saloon::fake([
            $this->page1Response(),
            $this->page2Response(),
        ]);

        $paginator = new EventsPaginator(
            connector: $this->makeConnector(),
            request: new EventsProbeRequest,
        );

        $pages = [];
        foreach ($paginator as $response) {
            $pages[] = $response->json('eventsTransmitted');
        }

        $this->assertSame([1000, 3], $pages);
    }

    #[Test]
    public function it_yields_all_items_across_pages(): void
    {
        Saloon::fake([
            $this->page1Response(),
            $this->page2Response(),
        ]);

        $items = iterator_to_array(
            (new EventsPaginator(
                connector: $this->makeConnector(),
                request: new EventsProbeRequest,
            ))->items(),
            false,
        );

        $this->assertCount(3, $items);
        $this->assertSame(101, $items[0]['id']);
        $this->assertSame(102, $items[1]['id']);
        $this->assertSame(103, $items[2]['id']);
    }

    #[Test]
    public function it_stops_after_a_single_page_when_events_transmitted_is_below_threshold(): void
    {
        Saloon::fake([
            $this->page2Response(),
        ]);

        $pages = [];
        foreach (new EventsPaginator(connector: $this->makeConnector(), request: new EventsProbeRequest) as $response) {
            $pages[] = $response->json('eventsTransmitted');
        }

        $this->assertCount(1, $pages);
    }

    #[Test]
    public function it_sends_the_page_number_in_the_page_header(): void
    {
        Saloon::fake([
            $this->page1Response(),
            $this->page2Response(),
        ]);

        $paginator = new EventsPaginator(
            connector: $this->makeConnector(),
            request: new EventsProbeRequest,
        );

        iterator_to_array($paginator->items(), false);

        Saloon::assertSentCount(2);

        // applyPagination mutates the PendingRequest (not the base Request),
        // so the Page header is accessible via the recorded Response.
        Saloon::assertSent(function (Request $request, Response $response) {
            $page = $response->getPendingRequest()->headers()->get('Page');

            return $page == '1' || $page == '2';
        });
    }

    private function makeConnector(): RaidHelperConnector
    {
        return new RaidHelperConnector(token: 'test-token', serverId: '111222333444555666');
    }

    private function page1Response(): MockResponse
    {
        return MockResponse::make(body: [
            'eventsTransmitted' => 1000,
            'postedEvents' => [
                ['id' => 101, 'title' => 'Event One'],
                ['id' => 102, 'title' => 'Event Two'],
            ],
        ], status: 200);
    }

    private function page2Response(): MockResponse
    {
        return MockResponse::make(body: [
            'eventsTransmitted' => 3,
            'postedEvents' => [
                ['id' => 103, 'title' => 'Event Three'],
            ],
        ], status: 200);
    }
}

/**
 * Minimal concrete request used only to exercise the paginator in this test.
 */
class EventsProbeRequest extends Request implements Paginatable
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/probe';
    }
}
