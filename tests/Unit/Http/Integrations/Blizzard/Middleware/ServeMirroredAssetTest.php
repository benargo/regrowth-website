<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Middleware;

use App\Contracts\Http\Integrations\Blizzard\Mirrorable;
use App\Http\Integrations\Blizzard\Middleware\ServeMirroredAsset;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use App\Http\Integrations\Blizzard\Support\MirrorPaths;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Contracts\RequestMiddleware;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

#[Group('blizzard-integration')]
class ServeMirroredAssetTest extends TestCase
{
    #[Test]
    public function it_implements_the_request_middleware_interface(): void
    {
        $this->assertInstanceOf(RequestMiddleware::class, new ServeMirroredAsset(
            new MirrorPaths(Region::EU),
            Storage::fake('public'),
        ));
    }

    #[Test]
    public function it_returns_a_fake_response_from_disk_when_the_file_exists(): void
    {
        $disk = Storage::fake('public');
        $disk->put('blizzard-cdn/icons/56/foo.jpg', 'CACHED');

        $mock = new MockClient([
            FetchIconRequest::class => MockResponse::make(body: 'NETWORK', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, $disk);
        $connector->withMockClient($mock);

        $response = $connector->send(new FetchIconRequest('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg'));

        $this->assertSame('CACHED', $response->body());
        $this->assertSame('hit', $response->header('X-Mirror'));
    }

    #[Test]
    public function it_passes_through_when_the_file_is_not_on_disk(): void
    {
        $disk = Storage::fake('public');

        $mock = new MockClient([
            FetchIconRequest::class => MockResponse::make(body: 'NETWORK', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, $disk);
        $connector->withMockClient($mock);

        $response = $connector->send(new FetchIconRequest('https://render.worldofwarcraft.com/eu/icons/56/missing.jpg'));

        $this->assertSame('NETWORK', $response->body());
        $this->assertNotSame('hit', $response->header('X-Mirror'));
    }

    #[Test]
    public function it_honours_a_mirrorable_request_path_override(): void
    {
        $disk = Storage::fake('public');
        $disk->put('custom/path.jpg', 'CUSTOM');

        $request = new class('https://render.worldofwarcraft.com/eu/anything.jpg') extends FetchIconRequest implements Mirrorable
        {
            public function resolveMirrorPath(): string
            {
                return 'custom/path.jpg';
            }
        };

        $mock = new MockClient([
            $request::class => MockResponse::make(body: 'NETWORK', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, $disk);
        $connector->withMockClient($mock);

        $response = $connector->send($request);

        $this->assertSame('CUSTOM', $response->body());
    }
}
