<?php

namespace Tests\Unit\Http\Integrations\Blizzard\Responses;

use App\Http\Integrations\Blizzard\Responses\FetchAssetResponse;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;
use Tests\TestCase;

#[Group('blizzard-integration')]
class FetchAssetResponseTest extends TestCase
{
    #[Test]
    public function it_extends_saloon_response(): void
    {
        $this->assertTrue(is_subclass_of(FetchAssetResponse::class, Response::class));
    }

    #[Test]
    public function mirrored_path_returns_null_before_it_is_set(): void
    {
        $response = $this->makeResponse();

        $this->assertNull($response->mirroredPath());
    }

    #[Test]
    public function mirrored_path_returns_the_path_after_it_is_set(): void
    {
        $response = $this->makeResponse();
        $response->setMirroredPath('blizzard-cdn/icons/56/foo.jpg');

        $this->assertSame('blizzard-cdn/icons/56/foo.jpg', $response->mirroredPath());
    }

    #[Test]
    public function mirrored_url_returns_null_when_path_is_not_set(): void
    {
        $response = $this->makeResponse();

        $this->assertNull($response->mirroredUrl());
    }

    #[Test]
    public function mirrored_url_returns_storage_url_when_path_is_set(): void
    {
        Storage::fake('public');

        $response = $this->makeResponse();
        $response->setMirroredPath('blizzard-cdn/icons/56/foo.jpg');

        $url = $response->mirroredUrl();

        $this->assertNotNull($url);
        $this->assertStringContainsString('blizzard-cdn/icons/56/foo.jpg', $url);
    }

    #[Test]
    public function is_from_mirror_returns_true_when_x_mirror_hit_header_is_present(): void
    {
        $response = $this->makeResponse(headers: ['X-Mirror' => 'hit']);

        $this->assertTrue($response->isFromMirror());
    }

    #[Test]
    public function is_from_mirror_returns_false_when_x_mirror_header_is_absent(): void
    {
        $response = $this->makeResponse();

        $this->assertFalse($response->isFromMirror());
    }

    private function makeResponse(array $headers = []): FetchAssetResponse
    {
        $psrResponse = new PsrResponse(
            status: 200,
            headers: $headers,
            body: 'BINARY',
        );

        $psrRequest = new Request('GET', 'https://render.worldofwarcraft.com/eu/icons/56/foo.jpg');

        $pendingRequest = Mockery::mock(PendingRequest::class);

        return new FetchAssetResponse($psrResponse, $pendingRequest, $psrRequest);
    }
}
