<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Render;

use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Uri;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

class FetchIconRequestTest extends TestCase
{
    #[Test]
    public function it_strips_the_host_for_apex_render_urls(): void
    {
        $request = new FetchIconRequest('https://render.worldofwarcraft.com/icons/56/foo.jpg');

        $this->assertSame('/icons/56/foo.jpg', $request->resolveEndpoint());
        $this->assertSame(Method::GET, $request->getMethod());
    }

    #[Test]
    public function it_strips_the_host_for_regional_subdomain_render_urls(): void
    {
        $request = new FetchIconRequest('https://render-eu.worldofwarcraft.com/icons/56/foo.jpg');

        $this->assertSame('/icons/56/foo.jpg', $request->resolveEndpoint());
    }

    #[Test]
    public function it_preserves_an_inline_region_segment_in_the_path(): void
    {
        $request = new FetchIconRequest('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg');

        $this->assertSame('/eu/icons/56/foo.jpg', $request->resolveEndpoint());
    }

    #[Test]
    public function it_accepts_a_uri_instance_as_an_absolute_render_url(): void
    {
        $request = new FetchIconRequest(
            Uri::of('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg'),
        );

        $this->assertSame('/eu/icons/56/foo.jpg', $request->resolveEndpoint());
    }

    #[Test]
    public function it_rejects_a_uri_instance_with_a_non_blizzard_host(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FetchIconRequest(Uri::of('https://example.com/icons/56/foo.jpg'));
    }

    #[Test]
    public function it_sends_a_uri_instance_through_the_render_connector(): void
    {
        $mock = new MockClient([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, Storage::fake('public'));
        $connector->withMockClient($mock);

        $connector->send(new FetchIconRequest(
            Uri::of('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg'),
        ));

        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/icons/56/foo.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }

    #[Test]
    public function it_rejects_non_blizzard_hosts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FetchIconRequest('https://example.com/icons/56/foo.jpg');
    }

    #[Test]
    public function it_rejects_urls_without_a_path(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FetchIconRequest('https://render.worldofwarcraft.com');
    }

    #[Test]
    public function it_rejects_urls_whose_path_is_only_a_slash(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FetchIconRequest('https://render.worldofwarcraft.com/');
    }

    #[Test]
    public function it_sends_through_render_connector_to_the_given_url(): void
    {
        $mock = new MockClient([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, Storage::fake('public'));
        $connector->withMockClient($mock);

        $response = $connector->send(new FetchIconRequest('https://render.worldofwarcraft.com/icons/56/foo.jpg'));

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
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, Storage::fake('public'));
        $connector->withMockClient($mock);

        $connector->send(new FetchIconRequest('https://render.worldofwarcraft.com/eu/icons/56/foo.jpg'));

        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/icons/56/foo.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }

    #[Test]
    public function it_builds_an_endpoint_from_an_icon_name_using_the_connectors_region(): void
    {
        $mock = new MockClient([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, Storage::fake('public'));
        $connector->withMockClient($mock);

        $connector->send(new FetchIconRequest('inv_misc_questionmark'));

        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/icons/56/inv_misc_questionmark.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }

    #[Test]
    public function it_builds_an_endpoint_from_an_icon_name_using_the_us_region(): void
    {
        $mock = new MockClient([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::US, Storage::fake('public'));
        $connector->withMockClient($mock);

        $connector->send(new FetchIconRequest('inv_misc_questionmark'));

        $this->assertSame(
            'https://render.worldofwarcraft.com/us/icons/56/inv_misc_questionmark.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }

    #[Test]
    public function it_builds_an_endpoint_from_an_icon_name_with_a_custom_size(): void
    {
        $mock = new MockClient([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, Storage::fake('public'));
        $connector->withMockClient($mock);

        $connector->send(new FetchIconRequest('inv_misc_questionmark', size: 36));

        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/icons/36/inv_misc_questionmark.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }

    #[Test]
    public function it_preserves_an_existing_file_extension_on_an_icon_name(): void
    {
        $mock = new MockClient([
            FetchIconRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, Storage::fake('public'));
        $connector->withMockClient($mock);

        $connector->send(new FetchIconRequest('inv_misc_questionmark.jpg'));

        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/icons/56/inv_misc_questionmark.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }
}
