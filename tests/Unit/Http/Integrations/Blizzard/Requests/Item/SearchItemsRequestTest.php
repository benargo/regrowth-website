<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Item;

use App\Http\Integrations\Blizzard\Requests\Item\SearchItemsRequest;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

#[Group('blizzard-integration')]
class SearchItemsRequestTest extends BlizzardTestCase
{
    #[Test]
    public function it_maps_name_to_name_en_gb_query_param(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            SearchItemsRequest::class => MockResponse::make(body: [
                'page' => 1, 'pageSize' => 50, 'maxPageSize' => 100,
                'pageCount' => 1, 'resultCount' => 1,
                'results' => [],
            ], status: 200),
        ]);

        $this->makeConnector()->send(new SearchItemsRequest(name: 'Thunderfury'));

        Saloon::assertSent(fn (SearchItemsRequest $r) => $r->query()->get('name.en_GB') === 'Thunderfury'
        );
    }

    #[Test]
    public function it_maps_orderby_query_param(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            SearchItemsRequest::class => MockResponse::make(body: [
                'page' => 1, 'pageSize' => 50, 'maxPageSize' => 100,
                'pageCount' => 1, 'resultCount' => 0,
                'results' => [],
            ], status: 200),
        ]);

        $this->makeConnector()->send(new SearchItemsRequest(orderby: 'id'));

        Saloon::assertSent(fn (SearchItemsRequest $r) => $r->query()->get('orderby') === 'id'
        );
    }

    #[Test]
    public function it_maps_page_to_underscore_page_query_param(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            SearchItemsRequest::class => MockResponse::make(body: [
                'page' => 2, 'pageSize' => 50, 'maxPageSize' => 100,
                'pageCount' => 3, 'resultCount' => 10,
                'results' => [],
            ], status: 200),
        ]);

        $this->makeConnector()->send(new SearchItemsRequest(page: 2));

        Saloon::assertSent(fn (SearchItemsRequest $r) => $r->query()->get('_page') === 2
        );
    }

    #[Test]
    public function it_clamps_page_size_to_1000(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            SearchItemsRequest::class => MockResponse::make(body: [
                'page' => 1, 'pageSize' => 1000, 'maxPageSize' => 1000,
                'pageCount' => 1, 'resultCount' => 0,
                'results' => [],
            ], status: 200),
        ]);

        $this->makeConnector()->send(new SearchItemsRequest(pageSize: 9999));

        Saloon::assertSent(fn (SearchItemsRequest $r) => $r->query()->get('_pageSize') === 1000
        );
    }

    #[Test]
    public function it_omits_null_params_from_query(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            SearchItemsRequest::class => MockResponse::make(body: [
                'page' => 1, 'pageSize' => 50, 'maxPageSize' => 100,
                'pageCount' => 1, 'resultCount' => 0,
                'results' => [],
            ], status: 200),
        ]);

        $this->makeConnector()->send(new SearchItemsRequest);

        Saloon::assertSent(fn (SearchItemsRequest $r) => $r->query()->get('name.en_GB') === null &&
            $r->query()->get('orderby') === null &&
            $r->query()->get('_page') === null &&
            $r->query()->get('_pageSize') === null
        );
    }

    #[Test]
    public function it_returns_response_with_search_results(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            SearchItemsRequest::class => MockResponse::make(body: [
                'page' => 1, 'pageSize' => 50, 'maxPageSize' => 100,
                'pageCount' => 1, 'resultCount' => 1,
                'results' => [
                    ['key' => ['href' => 'https://eu.api.blizzard.com/data/wow/item/19019'], 'data' => ['id' => 19019]],
                ],
            ], status: 200),
        ]);

        $response = $this->makeConnector()->send(new SearchItemsRequest(name: 'Thunderfury'));

        $this->assertSame(1, $response->json('resultCount'));
        $this->assertCount(1, $response->json('results'));
    }
}
