<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Render;

use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

class FetchAssetRequestTest extends TestCase
{
    #[Test]
    public function it_strips_the_host_for_apex_render_urls(): void
    {
        $request = new FetchAssetRequest('https://render.worldofwarcraft.com/icons/56/foo.jpg');

        $this->assertSame('/icons/56/foo.jpg', $request->resolveEndpoint());
        $this->assertSame(Method::GET, $request->getMethod());
    }

    #[Test]
    public function it_strips_the_host_for_regional_subdomain_render_urls(): void
    {
        $request = new FetchAssetRequest('https://render-eu.worldofwarcraft.com/icons/56/foo.jpg');

        $this->assertSame('/icons/56/foo.jpg', $request->resolveEndpoint());
    }

    #[Test]
    public function it_preserves_an_inline_region_segment_in_the_path(): void
    {
        $request = new FetchAssetRequest('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg');

        $this->assertSame('/eu/icons/56/foo.jpg', $request->resolveEndpoint());
    }

    #[Test]
    public function it_rejects_non_blizzard_hosts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FetchAssetRequest('https://example.com/icons/56/foo.jpg');
    }

    #[Test]
    public function it_rejects_urls_without_a_path(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FetchAssetRequest('https://render.worldofwarcraft.com');
    }

    #[Test]
    public function it_rejects_urls_whose_path_is_only_a_slash(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FetchAssetRequest('https://render.worldofwarcraft.com/');
    }

    #[Test]
    public function it_sends_through_render_connector_to_the_given_url(): void
    {
        $mock = new MockClient([
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, Storage::fake('public'));
        $connector->withMockClient($mock);

        $response = $connector->send(new FetchAssetRequest('https://render.worldofwarcraft.com/icons/56/foo.jpg'));

        $this->assertSame('BINARY', $response->body());
        $this->assertSame(
            'https://render.worldofwarcraft.com/icons/56/foo.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }

    #[Test]
    public function it_composes_paths_with_inline_region_segments_correctly(): void
    {
        $mock = new MockClient([
            FetchAssetRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, Storage::fake('public'));
        $connector->withMockClient($mock);

        $connector->send(new FetchAssetRequest('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg'));

        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/icons/56/foo.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }
}
