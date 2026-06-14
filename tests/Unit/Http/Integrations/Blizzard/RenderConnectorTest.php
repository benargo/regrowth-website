<?php

namespace Tests\Unit\Http\Integrations\Blizzard;

use App\Http\Integrations\Blizzard\Exceptions\MediaNotFoundException;
use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\RenderConnector;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

#[Group('blizzard-integration')]
class RenderConnectorTest extends TestCase
{
    // ==================== resolveBaseUrl ====================

    private function makeConnector(Region $region = Region::EU): RenderConnector
    {
        return new RenderConnector($region, Storage::fake('public'));
    }

    #[Test]
    public function resolves_a_region_agnostic_base_url(): void
    {
        $expected = 'https://render.worldofwarcraft.com';

        $this->assertSame($expected, $this->makeConnector(Region::EU)->resolveBaseUrl());
        $this->assertSame($expected, $this->makeConnector(Region::US)->resolveBaseUrl());
        $this->assertSame($expected, $this->makeConnector(Region::KR)->resolveBaseUrl());
        $this->assertSame($expected, $this->makeConnector(Region::TW)->resolveBaseUrl());
    }

    // ==================== getRegion ====================

    #[Test]
    public function exposes_the_injected_region(): void
    {
        $this->assertSame(Region::EU, $this->makeConnector(Region::EU)->getRegion());
        $this->assertSame(Region::KR, $this->makeConnector(Region::KR)->getRegion());
    }

    // ==================== Pipeline behaviour ====================

    #[Test]
    public function sends_unauthenticated_requests_against_the_render_host(): void
    {
        $mock = Saloon::fake([
            MockResponse::make(body: 'binary-bytes', status: 200),
        ]);

        $response = $this->makeConnector()->send(new TestRenderIconRequest('inv_misc_questionmark'));

        $this->assertSame(200, $response->status());
        $this->assertSame('binary-bytes', $response->body());

        $pending = $mock->getLastPendingRequest();
        $this->assertNotNull($pending);
        $this->assertSame(
            'https://render.worldofwarcraft.com/icons/56/inv_misc_questionmark.jpg',
            $pending->getUrl(),
        );
        $this->assertNull($pending->headers()->get('Authorization'));
    }

    #[Group('error-handling')]
    #[Test]
    public function throws_media_not_found_exception_on_404(): void
    {
        Saloon::fake([
            MockResponse::make(body: '', status: 404),
        ]);

        $this->expectException(MediaNotFoundException::class);

        $this->makeConnector()->send(new TestRenderIconRequest('does_not_exist'));
    }
}

/**
 * Lightweight test-only request used to drive the RenderConnector pipeline.
 * The real GetIconRequest lands in Phase 2.
 */
class TestRenderIconRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(public readonly string $iconName) {}

    public function resolveEndpoint(): string
    {
        return "/icons/56/{$this->iconName}.jpg";
    }
}
