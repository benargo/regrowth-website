<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Media;

use App\Http\Integrations\Blizzard\Requests\Media\SearchMediaRequest;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Unit\Http\Integrations\Blizzard\BlizzardTestCase;

class SearchMediaRequestTest extends BlizzardTestCase
{
    private function emptySearchResponse(): MockResponse
    {
        return MockResponse::make(body: [
            'page' => 1, 'pageSize' => 50, 'maxPageSize' => 100,
            'pageCount' => 1, 'resultCount' => 0,
            'results' => [],
        ], status: 200);
    }

    #[Test]
    public function it_throws_when_tags_is_empty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "tags" parameter is required for media search.');

        new SearchMediaRequest(tags: []);
    }

    #[Test]
    public function it_throws_when_tags_contains_invalid_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tag(s): weapon');

        new SearchMediaRequest(tags: ['item', 'weapon']);
    }

    #[Test]
    public function it_joins_tags_with_comma_in_query(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            SearchMediaRequest::class => $this->emptySearchResponse(),
        ]);

        $this->makeConnector()->send(new SearchMediaRequest(tags: ['item', 'spell']));

        Saloon::assertSent(fn (SearchMediaRequest $r) => $r->query()->get('tags') === 'item,spell'
        );
    }

    #[Test]
    public function it_maps_name_to_name_en_us_query_param(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            SearchMediaRequest::class => $this->emptySearchResponse(),
        ]);

        $this->makeConnector()->send(new SearchMediaRequest(tags: ['item'], name: 'Thunderfury'));

        Saloon::assertSent(fn (SearchMediaRequest $r) => $r->query()->get('name.en_US') === 'Thunderfury'
        );
    }

    #[Test]
    public function it_maps_item_id_query_param(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            SearchMediaRequest::class => $this->emptySearchResponse(),
        ]);

        $this->makeConnector()->send(new SearchMediaRequest(tags: ['item'], itemId: 19019));

        Saloon::assertSent(fn (SearchMediaRequest $r) => $r->query()->get('itemId') === 19019
        );
    }

    #[Test]
    public function it_maps_orderby_query_param(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            SearchMediaRequest::class => $this->emptySearchResponse(),
        ]);

        $this->makeConnector()->send(new SearchMediaRequest(tags: ['spell'], orderby: 'id'));

        Saloon::assertSent(fn (SearchMediaRequest $r) => $r->query()->get('orderby') === 'id'
        );
    }

    #[Test]
    public function it_maps_page_to_underscore_page_query_param(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            SearchMediaRequest::class => $this->emptySearchResponse(),
        ]);

        $this->makeConnector()->send(new SearchMediaRequest(tags: ['item'], page: 3));

        Saloon::assertSent(fn (SearchMediaRequest $r) => $r->query()->get('_page') === 3
        );
    }

    #[Test]
    public function it_clamps_page_size_to_1000(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            SearchMediaRequest::class => $this->emptySearchResponse(),
        ]);

        $this->makeConnector()->send(new SearchMediaRequest(tags: ['item'], pageSize: 9999));

        Saloon::assertSent(fn (SearchMediaRequest $r) => $r->query()->get('_pageSize') === 1000
        );
    }

    #[Test]
    public function it_omits_null_optional_params_from_query(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => $this->tokenMock(),
            SearchMediaRequest::class => $this->emptySearchResponse(),
        ]);

        $this->makeConnector()->send(new SearchMediaRequest(tags: ['item']));

        Saloon::assertSent(fn (SearchMediaRequest $r) => $r->query()->get('itemId') === null &&
            $r->query()->get('name.en_US') === null &&
            $r->query()->get('orderby') === null &&
            $r->query()->get('_page') === null &&
            $r->query()->get('_pageSize') === null
        );
    }
}
