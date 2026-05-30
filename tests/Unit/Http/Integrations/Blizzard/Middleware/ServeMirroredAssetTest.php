<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Middleware;

use App\Contracts\Http\Integrations\Blizzard\Mirrorable;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

class ServeMirroredAssetTest extends TestCase
{
    #[Test]
    public function it_returns_a_fake_response_from_disk_when_the_file_exists(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('blizzard-cdn/icons/56/foo.jpg', 'CACHED');

        $mock = new MockClient([
            FetchAssetRequest::class => MockResponse::make(body: 'NETWORK', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, app(FilesystemManager::class));
        $connector->withMockClient($mock);

        $response = $connector->send(new FetchAssetRequest('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg'));

        $this->assertSame('CACHED', $response->body());
        $this->assertSame('hit', $response->header('X-Mirror'));
    }

    #[Test]
    public function it_passes_through_when_the_file_is_not_on_disk(): void
    {
        Storage::fake('public');

        $mock = new MockClient([
            FetchAssetRequest::class => MockResponse::make(body: 'NETWORK', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, app(FilesystemManager::class));
        $connector->withMockClient($mock);

        $response = $connector->send(new FetchAssetRequest('https://render.worldofwarcraft.com/eu/icons/56/missing.jpg'));

        $this->assertSame('NETWORK', $response->body());
        $this->assertNotSame('hit', $response->header('X-Mirror'));
    }

    #[Test]
    public function it_honours_a_mirrorable_request_path_override(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('custom/path.jpg', 'CUSTOM');

        $request = new class('https://render.worldofwarcraft.com/eu/anything.jpg') extends FetchAssetRequest implements Mirrorable
        {
            public function resolveMirrorPath(): string
            {
                return 'custom/path.jpg';
            }
        };

        $mock = new MockClient([
            $request::class => MockResponse::make(body: 'NETWORK', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, app(FilesystemManager::class));
        $connector->withMockClient($mock);

        $response = $connector->send($request);

        $this->assertSame('CUSTOM', $response->body());
    }
}
