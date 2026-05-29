<?php

namespace Tests\Unit\Http\Integrations\Blizzard;

use App\Http\Integrations\Blizzard\Region;
use App\Http\Integrations\Blizzard\RenderConnector;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class RenderConnectorTest extends TestCase
{
    // ==================== resolveBaseUrl ====================

    #[Test]
    public function resolves_base_url_from_region(): void
    {
        $this->assertSame(
            'https://render.worldofwarcraft.com/eu',
            (new RenderConnector(Region::EU))->resolveBaseUrl(),
        );
        $this->assertSame(
            'https://render.worldofwarcraft.com/us',
            (new RenderConnector(Region::US))->resolveBaseUrl(),
        );
        $this->assertSame(
            'https://render.worldofwarcraft.com/kr',
            (new RenderConnector(Region::KR))->resolveBaseUrl(),
        );
        $this->assertSame(
            'https://render.worldofwarcraft.com/tw',
            (new RenderConnector(Region::TW))->resolveBaseUrl(),
        );
    }

    // ==================== getRegion ====================

    #[Test]
    public function exposes_the_injected_region(): void
    {
        $this->assertSame(Region::EU, (new RenderConnector(Region::EU))->getRegion());
        $this->assertSame(Region::KR, (new RenderConnector(Region::KR))->getRegion());
    }

    // ==================== Pipeline behaviour ====================

    #[Test]
    public function sends_unauthenticated_requests_against_the_region_render_host(): void
    {
        $mock = Saloon::fake([
            MockResponse::make(body: 'binary-bytes', status: 200),
        ]);

        $response = (new RenderConnector(Region::EU))->send(new TestRenderIconRequest('inv_misc_questionmark'));

        $this->assertSame(200, $response->status());
        $this->assertSame('binary-bytes', $response->body());

        $pending = $mock->getLastPendingRequest();
        $this->assertNotNull($pending);
        $this->assertSame(
            'https://render.worldofwarcraft.com/eu/icons/56/inv_misc_questionmark.jpg',
            $pending->getUrl(),
        );
        $this->assertNull($pending->headers()->get('Authorization'));
    }

    #[Test]
    public function returns_failed_responses_without_throwing(): void
    {
        Saloon::fake([
            MockResponse::make(body: '', status: 404),
        ]);

        $response = (new RenderConnector(Region::EU))->send(new TestRenderIconRequest('does_not_exist'));

        $this->assertSame(404, $response->status());
        $this->assertTrue($response->failed());
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
