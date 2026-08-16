<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Pagination;

use App\Http\Integrations\Blizzard\Requests\Item\SearchItemsRequest;
use App\Http\Integrations\Blizzard\Requests\Media\SearchMediaRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

#[Group('blizzard-integration')]
class SearchPaginatorTest extends BlizzardTestCase
{
    private function page1Response(): MockResponse
    {
        return MockResponse::make(body: [
            'page' => 1, 'pageSize' => 50, 'maxPageSize' => 100,
            'pageCount' => 2, 'resultCount' => 3,
            'results' => [
                ['data' => ['id' => 1, 'name' => 'Result One']],
                ['data' => ['id' => 2, 'name' => 'Result Two']],
            ],
        ], status: 200);
    }

    private function page2Response(): MockResponse
    {
        return MockResponse::make(body: [
            'page' => 2, 'pageSize' => 50, 'maxPageSize' => 100,
            'pageCount' => 2, 'resultCount' => 3,
            'results' => [
                ['data' => ['id' => 3, 'name' => 'Result Three']],
            ],
        ], status: 200);
    }

    #[Test]
    public function it_iterates_through_multiple_pages_for_item_search(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            $this->page1Response(),
            $this->page2Response(),
        ]);

        $paginator = (new SearchItemsRequest(name: 'Item'))->paginate($this->makeConnector());

        $pages = [];
        foreach ($paginator as $response) {
            $pages[] = $response->json('page');
        }

        $this->assertSame([1, 2], $pages);
    }

    #[Test]
    public function it_yields_all_items_across_pages_for_item_search(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            $this->page1Response(),
            $this->page2Response(),
        ]);

        $items = iterator_to_array(
            (new SearchItemsRequest)->paginate($this->makeConnector())->items(),
            false,
        );

        $this->assertCount(3, $items);
        $this->assertSame(1, $items[0]['data']['id']);
        $this->assertSame(2, $items[1]['data']['id']);
        $this->assertSame(3, $items[2]['data']['id']);
    }

    #[Test]
    public function it_exposes_metadata_from_last_response_for_item_search(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            $this->page1Response(),
            $this->page2Response(),
        ]);

        $paginator = (new SearchItemsRequest)->paginate($this->makeConnector());
        iterator_to_array($paginator->items(), false);

        $this->assertSame(3, $paginator->getResultCount());
        $this->assertSame(2, $paginator->getPageCount());
        $this->assertSame(100, $paginator->getMaxPageSize());
    }

    #[Test]
    public function it_iterates_through_multiple_pages_for_media_search(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            $this->page1Response(),
            $this->page2Response(),
        ]);

        $paginator = (new SearchMediaRequest(tags: ['item']))->paginate($this->makeConnector());

        $pages = [];
        foreach ($paginator as $response) {
            $pages[] = $response->json('page');
        }

        $this->assertSame([1, 2], $pages);
    }

    #[Test]
    public function it_yields_all_items_across_pages_for_media_search(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            $this->page1Response(),
            $this->page2Response(),
        ]);

        $items = iterator_to_array(
            (new SearchMediaRequest(tags: ['item']))->paginate($this->makeConnector())->items(),
            false,
        );

        $this->assertCount(3, $items);
        $this->assertSame(1, $items[0]['data']['id']);
        $this->assertSame(2, $items[1]['data']['id']);
        $this->assertSame(3, $items[2]['data']['id']);
    }

    #[Test]
    public function it_exposes_metadata_from_last_response_for_media_search(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            $this->page1Response(),
            $this->page2Response(),
        ]);

        $paginator = (new SearchMediaRequest(tags: ['item']))->paginate($this->makeConnector());
        iterator_to_array($paginator->items(), false);

        $this->assertSame(3, $paginator->getResultCount());
        $this->assertSame(2, $paginator->getPageCount());
        $this->assertSame(100, $paginator->getMaxPageSize());
    }
}
