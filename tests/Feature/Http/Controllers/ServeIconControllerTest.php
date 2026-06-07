<?php

namespace Tests\Feature\Http\Controllers;

use App\Http\Integrations\Blizzard\Requests\Render\FetchIconRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class ServeIconControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Assert that the Cache-Control header contains a specific directive.
     * Laravel may reorder Cache-Control directives, so we check for presence rather than exact match.
     */
    private function assertCacheControlContains(TestResponse $response, string $directive): void
    {
        $cacheControl = $response->headers->get('Cache-Control', '');
        $directives = array_map('trim', explode(',', $cacheControl));
        $this->assertContains($directive, $directives, "Cache-Control header does not contain '{$directive}'. Got: {$cacheControl}");
    }

    #[Test]
    public function it_serves_icon_from_disk_with_long_cache(): void
    {
        Storage::fake('public');
        $diskBytes = 'DISK_BINARY_CONTENT';
        $apiBytes = 'API_BINARY_CONTENT';
        Storage::disk('public')->put('blizzard-cdn/icons/56/inv_bracer_02.jpg', $diskBytes);

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(body: $apiBytes, status: 200, headers: ['Content-Type' => 'image/jpeg']),
        ]);

        $url = URL::signedRoute('icons.show', ['size' => 56, 'name' => 'inv_bracer_02.jpg']);
        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertCacheControlContains($response, 'public');
        $this->assertCacheControlContains($response, 'max-age=31536000');
        $this->assertCacheControlContains($response, 'immutable');
        $this->assertSame($diskBytes, $response->getContent());
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_fetches_icon_from_api_on_disk_miss(): void
    {
        Storage::fake('public');
        $bytes = 'API_BINARY_CONTENT';

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(body: $bytes, status: 200, headers: ['Content-Type' => 'image/jpeg']),
        ]);

        $url = URL::signedRoute('icons.show', ['size' => 56, 'name' => 'inv_bracer_02.jpg']);
        $response = $this->get($url);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertCacheControlContains($response, 'public');
        $this->assertCacheControlContains($response, 'max-age=31536000');
        $this->assertCacheControlContains($response, 'immutable');
        $this->assertSame($bytes, $response->getContent());
        $this->assertTrue(Storage::disk('public')->exists('blizzard-cdn/icons/56/inv_bracer_02.jpg'));
    }

    #[Test]
    public function it_returns_questionmark_icon_on_404(): void
    {
        Storage::fake('public');

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(body: '', status: 404),
        ]);

        $url = URL::signedRoute('icons.show', ['size' => 56, 'name' => 'inv_bracer_02.jpg']);
        $response = $this->get($url);

        $expectedBytes = file_get_contents(resource_path('images/inv_misc_questionmark.jpg'));

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertCacheControlContains($response, 'public');
        $this->assertCacheControlContains($response, 'max-age=60');
        $this->assertSame($expectedBytes, $response->getContent());
    }

    #[Test]
    public function it_returns_questionmark_icon_on_server_error(): void
    {
        Storage::fake('public');

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(body: '', status: 500),
        ]);

        $url = URL::signedRoute('icons.show', ['size' => 56, 'name' => 'inv_bracer_02.jpg']);
        $response = $this->get($url);

        $expectedBytes = file_get_contents(resource_path('images/inv_misc_questionmark.jpg'));

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertCacheControlContains($response, 'public');
        $this->assertCacheControlContains($response, 'max-age=60');
        $this->assertSame($expectedBytes, $response->getContent());
    }

    #[Test]
    public function it_serves_bundled_questionmark_without_calling_the_cdn(): void
    {
        Storage::fake('public');

        Saloon::fake([
            FetchIconRequest::class => MockResponse::make(body: 'NOT-THE-QUESTIONMARK', status: 200, headers: ['Content-Type' => 'image/jpeg']),
        ]);

        $url = URL::signedRoute('icons.show', ['size' => 56, 'name' => 'inv_misc_questionmark.jpg']);
        $response = $this->get($url);

        $expectedBytes = file_get_contents(resource_path('images/inv_misc_questionmark.jpg'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
        $this->assertCacheControlContains($response, 'public');
        $this->assertCacheControlContains($response, 'max-age=31536000');
        $this->assertCacheControlContains($response, 'immutable');
        $this->assertSame($expectedBytes, $response->getContent());
        Saloon::assertNothingSent();
    }

    #[Test]
    public function it_rejects_unsigned_urls(): void
    {
        $response = $this->get('/icons/56/inv_bracer_02.jpg');

        $response->assertStatus(403);
    }

    #[Test]
    public function it_rejects_tampered_signatures(): void
    {
        $url = URL::signedRoute('icons.show', ['size' => 56, 'name' => 'inv_bracer_02.jpg']);
        $tamperedUrl = preg_replace('/signature=[^&]+/', 'signature=tampered123', $url);

        $response = $this->get($tamperedUrl);

        $response->assertStatus(403);
    }

    #[Test]
    public function it_rejects_invalid_extension(): void
    {
        // The route has ->where('name', '[a-z0-9_]+\.(jpg|png)') so .txt won't match the route at all.
        // We attempt a signed URL for a .txt name — the route regex rejects it → 404 (not matched).
        $url = URL::signedRoute('icons.show', ['size' => 56, 'name' => 'inv_bracer_02.txt']);

        $response = $this->get($url);

        $response->assertStatus(404);
    }

    #[Test]
    public function it_rejects_malformed_name(): void
    {
        // The route regex '[a-z0-9_]+\.(jpg|png)' rejects names with '@' → 404.
        $url = URL::signedRoute('icons.show', ['size' => 56, 'name' => 'inv_bracer@02.jpg']);

        $response = $this->get($url);

        $response->assertStatus(404);
    }
}
