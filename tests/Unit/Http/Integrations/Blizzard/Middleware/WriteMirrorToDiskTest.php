<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Middleware;

use App\Http\Integrations\Blizzard\Exceptions\MediaNotFoundException;
use App\Http\Integrations\Blizzard\Middleware\WriteMirrorToDisk;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Http\Integrations\Blizzard\Support\MirrorPaths;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Contracts\ResponseMiddleware;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Tests\TestCase;

class WriteMirrorToDiskTest extends TestCase
{
    #[Test]
    public function it_implements_the_response_middleware_interface(): void
    {
        $this->assertInstanceOf(ResponseMiddleware::class, new WriteMirrorToDisk(
            new MirrorPaths(Region::EU),
            Storage::fake('public'),
        ));
    }

    #[Test]
    public function it_writes_a_successful_response_body_to_the_disk_at_the_resolved_path(): void
    {
        $disk = Storage::fake('public');

        $mock = new MockClient([
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, $disk);
        $connector->withMockClient($mock);

        $connector->send(new FetchAssetRequest('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg'));

        $disk->assertExists('blizzard-cdn/icons/56/foo.jpg');
        $this->assertSame('BINARY', $disk->get('blizzard-cdn/icons/56/foo.jpg'));
    }

    #[Test]
    public function it_throws_media_not_found_exception_on_404(): void
    {
        $disk = Storage::fake('public');

        $mock = new MockClient([
            FetchAssetRequest::class => MockResponse::make(body: 'missing', status: 404),
        ]);

        $connector = new RenderConnector(Region::EU, $disk);
        $connector->withMockClient($mock);

        $this->expectException(MediaNotFoundException::class);

        $connector->send(new FetchAssetRequest('https://render.worldofwarcraft.com/eu/icons/56/missing.jpg'));
    }

    #[Test]
    public function it_swallows_transient_5xx_errors_without_writing(): void
    {
        $disk = Storage::fake('public');

        $mock = new MockClient([
            FetchAssetRequest::class => MockResponse::make(body: 'oops', status: 503),
        ]);

        $connector = new RenderConnector(Region::EU, $disk);
        $connector->withMockClient($mock);

        $connector->send(new FetchAssetRequest('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg'));

        $disk->assertMissing('blizzard-cdn/icons/56/foo.jpg');
    }

    #[Test]
    public function it_does_not_overwrite_when_the_file_already_exists(): void
    {
        $disk = Storage::fake('public');
        $disk->put('blizzard-cdn/icons/56/foo.jpg', 'ALREADY_THERE');

        $resolver = new MirrorPaths(Region::EU);
        $middleware = new WriteMirrorToDisk($resolver, $disk);

        // Build a real PendingRequest so the middleware can resolve the URL
        $connector = new RenderConnector(Region::EU, $disk);
        $fetchRequest = new FetchAssetRequest('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg');
        $pendingRequest = new PendingRequest($connector, $fetchRequest);

        $factory = new HttpFactory;
        $psrResponse = MockResponse::make(body: 'NEW', status: 200)->createPsrResponse($factory, $factory);
        $psrRequest = $pendingRequest->createPsrRequest();
        $saloonResponse = Response::fromPsrResponse($psrResponse, $pendingRequest, $psrRequest);

        $middleware($saloonResponse);

        $this->assertSame('ALREADY_THERE', $disk->get('blizzard-cdn/icons/56/foo.jpg'));
    }
}
