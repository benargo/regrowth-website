<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Requests\Render;

use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\RenderConnector;
use App\Http\Integrations\Blizzard\Requests\Render\FetchCharacterPortraitRequest;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Uri;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockClient;
use Saloon\Http\Faking\MockResponse;
use Tests\TestCase;

class FetchCharacterPortraitRequestTest extends TestCase
{
    #[Test]
    public function it_strips_the_host_for_apex_render_urls(): void
    {
        $request = new FetchCharacterPortraitRequest(
            'https://render.worldofwarcraft.com/classicann-eu/character/thunderstrike/135/51042439-avatar.jpg',
        );

        $this->assertSame(
            '/classicann-eu/character/thunderstrike/135/51042439-avatar.jpg',
            $request->resolveEndpoint(),
        );
        $this->assertSame(Method::GET, $request->getMethod());
    }

    #[Test]
    public function it_strips_the_host_for_regional_subdomain_render_urls(): void
    {
        $request = new FetchCharacterPortraitRequest(
            'https://render-eu.worldofwarcraft.com/character/thunderstrike/135/51042439-avatar.jpg',
        );

        $this->assertSame(
            '/character/thunderstrike/135/51042439-avatar.jpg',
            $request->resolveEndpoint(),
        );
    }

    #[Test]
    public function it_preserves_an_inline_region_segment_in_the_path(): void
    {
        $request = new FetchCharacterPortraitRequest(
            'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg',
        );

        $this->assertSame(
            '/eu/character/thunderstrike/135/51042439-avatar.jpg',
            $request->resolveEndpoint(),
        );
    }

    #[Test]
    public function it_accepts_a_uri_instance_as_an_absolute_render_url(): void
    {
        $request = new FetchCharacterPortraitRequest(
            Uri::of('https://render.worldofwarcraft.com/classicann-eu/character/thunderstrike/135/51042439-avatar.jpg'),
        );

        $this->assertSame(
            '/classicann-eu/character/thunderstrike/135/51042439-avatar.jpg',
            $request->resolveEndpoint(),
        );
    }

    #[Test]
    public function it_rejects_a_uri_instance_with_a_non_blizzard_host(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FetchCharacterPortraitRequest(
            Uri::of('https://example.com/character/thunderstrike/135/51042439-avatar.jpg'),
        );
    }

    #[Test]
    public function it_sends_a_uri_instance_through_the_render_connector(): void
    {
        $mock = new MockClient([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, Storage::fake('public'));
        $connector->withMockClient($mock);

        $connector->send(new FetchCharacterPortraitRequest(
            Uri::of('https://render.worldofwarcraft.com/classicann-eu/character/thunderstrike/135/51042439-avatar.jpg'),
        ));

        $this->assertSame(
            'https://render.worldofwarcraft.com/classicann-eu/character/thunderstrike/135/51042439-avatar.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }

    #[Test]
    public function it_rejects_non_blizzard_hosts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FetchCharacterPortraitRequest('https://example.com/character/thunderstrike/135/51042439-avatar.jpg');
    }

    #[Test]
    public function it_rejects_urls_without_a_path(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FetchCharacterPortraitRequest('https://render.worldofwarcraft.com');
    }

    #[Test]
    public function it_rejects_urls_whose_path_is_only_a_slash(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FetchCharacterPortraitRequest('https://render.worldofwarcraft.com/');
    }

    #[Test]
    public function it_rejects_bare_inputs_without_a_slash(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FetchCharacterPortraitRequest('51042439-avatar');
    }

    #[Test]
    public function it_sends_through_render_connector_to_the_given_url(): void
    {
        $mock = new MockClient([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, Storage::fake('public'));
        $connector->withMockClient($mock);

        $response = $connector->send(new FetchCharacterPortraitRequest(
            'https://render.worldofwarcraft.com/classicann-eu/character/thunderstrike/135/51042439-avatar.jpg',
        ));

        $this->assertSame('BINARY', $response->body());
        $this->assertSame(
            'https://render.worldofwarcraft.com/classicann-eu/character/thunderstrike/135/51042439-avatar.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }

    #[Test]
    public function it_builds_an_endpoint_from_a_bare_portrait_path_using_the_connectors_region(): void
    {
        $mock = new MockClient([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, Storage::fake('public'));
        $connector->withMockClient($mock);

        $connector->send(new FetchCharacterPortraitRequest('thunderstrike/51042439-avatar'));

        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }

    #[Test]
    public function it_builds_an_endpoint_from_a_bare_portrait_path_using_the_us_region(): void
    {
        $mock = new MockClient([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::US, Storage::fake('public'));
        $connector->withMockClient($mock);

        $connector->send(new FetchCharacterPortraitRequest('thunderstrike/51042439-avatar'));

        $this->assertSame(
            'https://render.worldofwarcraft.com/us/character/thunderstrike/135/51042439-avatar.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }

    #[Test]
    public function it_builds_an_endpoint_with_a_custom_size(): void
    {
        $mock = new MockClient([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, Storage::fake('public'));
        $connector->withMockClient($mock);

        $connector->send(new FetchCharacterPortraitRequest('thunderstrike/51042439-avatar', size: 56));

        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/character/thunderstrike/56/51042439-avatar.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }

    #[Test]
    public function it_preserves_an_existing_file_extension_on_a_bare_portrait_path(): void
    {
        $mock = new MockClient([
            FetchCharacterPortraitRequest::class => MockResponse::make(body: 'BINARY', status: 200),
        ]);

        $connector = new RenderConnector(Region::EU, Storage::fake('public'));
        $connector->withMockClient($mock);

        $connector->send(new FetchCharacterPortraitRequest('thunderstrike/51042439-avatar.jpg'));

        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/character/thunderstrike/135/51042439-avatar.jpg',
            $mock->getLastPendingRequest()->getUrl(),
        );
    }
}
