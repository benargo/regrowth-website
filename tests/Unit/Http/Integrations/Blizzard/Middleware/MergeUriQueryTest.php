<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Middleware;

use App\Http\Integrations\Blizzard\Middleware\MergeUriQuery;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Uri;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Contracts\RequestMiddleware;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

class MergeUriQueryTest extends TestCase
{
    #[Test]
    public function it_implements_the_request_middleware_interface(): void
    {
        $this->assertInstanceOf(
            RequestMiddleware::class,
            new MergeUriQuery(Uri::of('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg')),
        );
    }

    #[Test]
    public function it_merges_query_parameters_from_the_uri_into_the_pending_request(): void
    {
        $disk = Storage::fake('public');

        $mock = new MockClient([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, $disk);
        $connector->withMockClient($mock);

        $uri = Uri::of('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg?alt=/shadow/avatar/2-1.jpg&version=3');
        $request = new FetchIconRequest($uri);
        $request->middleware()->onRequest(new MergeUriQuery($uri), 'mergeUriQuery');

        $response = $connector->send($request);

        $this->assertSame('/shadow/avatar/2-1.jpg', $response->getPendingRequest()->query()->get('alt'));
        $this->assertSame('3', $response->getPendingRequest()->query()->get('version'));
    }

    #[Test]
    public function it_does_not_add_any_query_parameters_when_the_uri_has_none(): void
    {
        $disk = Storage::fake('public');

        $mock = new MockClient([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, $disk);
        $connector->withMockClient($mock);

        $uri = Uri::of('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg');
        $request = new FetchIconRequest($uri);
        $request->middleware()->onRequest(new MergeUriQuery($uri), 'mergeUriQuery');

        $response = $connector->send($request);

        $this->assertEmpty($response->getPendingRequest()->query()->all());
    }

    #[Test]
    public function it_merges_without_overwriting_pre_existing_query_parameters(): void
    {
        $disk = Storage::fake('public');

        $mock = new MockClient([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, $disk);
        $connector->withMockClient($mock);

        $uri = Uri::of('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg?alt=/shadow/avatar/2-1.jpg');
        $request = new FetchIconRequest($uri);
        $request->query()->add('size', '56');
        $request->middleware()->onRequest(new MergeUriQuery($uri), 'mergeUriQuery');

        $response = $connector->send($request);

        $this->assertSame('/shadow/avatar/2-1.jpg', $response->getPendingRequest()->query()->get('alt'));
        $this->assertSame('56', $response->getPendingRequest()->query()->get('size'));
    }
}
