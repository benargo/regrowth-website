<?php

namespace Tests\Unit\Services\Blizzard;

use App\Services\Blizzard\MediaService;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MediaServiceTest extends TestCase
{
    private function makeService(?string $region = null, ?string $diskName = null): MediaService
    {
        return new MediaService(
            $region ?? 'eu',
            app(FilesystemManager::class),
            $diskName ?? 'public',
        );
    }

    // ==================== Single Asset: get (string) ====================

    #[Test]
    public function get_by_name_downloads_and_returns_local_url(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('fake-image-content', 200),
        ]);

        $service = $this->makeService();
        $url = $service->get('inv_misc_food_15');

        $this->assertIsString($url);
        $this->assertStringContainsString('blizzard/icons/inv_misc_food_15.jpg', $url);
        Storage::disk('public')->assertExists('blizzard/icons/inv_misc_food_15.jpg');
    }

    #[Test]
    public function get_by_name_returns_cached_url_without_re_downloading(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('blizzard/icons/inv_misc_food_15.jpg', 'existing-content');

        $httpCallCount = 0;
        Http::fake([
            'render.worldofwarcraft.com/*' => function () use (&$httpCallCount) {
                $httpCallCount++;

                return Http::response('new-content', 200);
            },
        ]);

        $service = $this->makeService();
        $url = $service->get('inv_misc_food_15');

        $this->assertIsString($url);
        $this->assertEquals(0, $httpCallCount);
    }

    #[Test]
    public function get_by_name_returns_null_on_download_failure(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('Not Found', 404),
        ]);

        $service = $this->makeService();
        $url = $service->get('nonexistent_icon');

        $this->assertNull($url);
    }

    #[Test]
    public function get_by_name_uses_correct_region_in_remote_url(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('fake-image-content', 200),
        ]);

        $service = $this->makeService('us');
        $service->get('inv_sword_39');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'render.worldofwarcraft.com/us/icons/56/inv_sword_39.jpg');
        });
    }

    // ==================== Single Asset: download (string) ====================

    #[Test]
    public function download_by_name_returns_local_path(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('fake-image-content', 200),
        ]);

        $service = $this->makeService();
        $path = $service->download('inv_sword_39');

        $this->assertEquals('blizzard/icons/inv_sword_39.jpg', $path);
        Storage::disk('public')->assertExists('blizzard/icons/inv_sword_39.jpg');
        $this->assertEquals('fake-image-content', Storage::disk('public')->get($path));
    }

    #[Test]
    public function download_by_name_returns_null_on_failure(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('Not Found', 404),
        ]);

        $service = $this->makeService();
        $path = $service->download('nonexistent_icon');

        $this->assertNull($path);
    }

    #[Test]
    public function download_by_name_skips_existing_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('blizzard/icons/inv_sword_39.jpg', 'existing-content');

        $httpCallCount = 0;
        Http::fake([
            'render.worldofwarcraft.com/*' => function () use (&$httpCallCount) {
                $httpCallCount++;

                return Http::response('new-content', 200);
            },
        ]);

        $service = $this->makeService();
        $path = $service->download('inv_sword_39');

        $this->assertEquals('blizzard/icons/inv_sword_39.jpg', $path);
        $this->assertEquals(0, $httpCallCount);
        $this->assertEquals('existing-content', Storage::disk('public')->get($path));
    }

    // ==================== Multiple Assets: get (array) ====================

    #[Test]
    public function get_assets_returns_local_urls(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('fake-image-content', 200),
        ]);

        $service = $this->makeService();
        $urls = $service->get([
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/classic-us/icons/56/inv_sword_39.jpg',
            'file_data_id' => 135349,
        ]);

        $this->assertIsArray($urls);
        $this->assertArrayHasKey(135349, $urls);
        $this->assertStringContainsString('blizzard/media/135349.jpg', $urls[135349]);
    }

    #[Test]
    public function get_assets_returns_null_on_failure(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('Not Found', 404),
        ]);

        $service = $this->makeService();
        $urls = $service->get([
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/classic-us/icons/56/inv_sword_39.jpg',
            'file_data_id' => 135349,
        ]);

        $this->assertNull($urls[135349]);
    }

    #[Test]
    public function get_assets_handles_multiple_assets(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('fake-image-content', 200),
        ]);

        $service = $this->makeService();
        $urls = $service->get([
            [
                'key' => 'icon',
                'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg',
                'file_data_id' => 135349,
            ],
            [
                'key' => 'thumbnail',
                'value' => 'https://render.worldofwarcraft.com/icons/32/inv_sword_39.jpg',
                'file_data_id' => 135350,
            ],
        ]);

        $this->assertCount(2, $urls);
        $this->assertStringContainsString('blizzard/media/135349.jpg', $urls[135349]);
        $this->assertStringContainsString('blizzard/media/135350.jpg', $urls[135350]);
    }

    // ==================== Multiple Assets: download (array) ====================

    #[Test]
    public function download_assets_stores_file_on_disk(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('fake-image-content', 200),
        ]);

        $service = $this->makeService();
        $paths = $service->download([
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/classic-us/icons/56/inv_sword_39.jpg',
            'file_data_id' => 135349,
        ]);

        $this->assertIsArray($paths);
        $this->assertArrayHasKey(135349, $paths);
        $this->assertEquals('blizzard/media/135349.jpg', $paths[135349]);
        Storage::disk('public')->assertExists('blizzard/media/135349.jpg');
        $this->assertEquals('fake-image-content', Storage::disk('public')->get($paths[135349]));
    }

    #[Test]
    public function download_assets_skips_existing_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('blizzard/media/135349.jpg', 'existing-content');

        $httpCallCount = 0;
        Http::fake([
            'render.worldofwarcraft.com/*' => function () use (&$httpCallCount) {
                $httpCallCount++;

                return Http::response('new-content', 200);
            },
        ]);

        $service = $this->makeService();
        $paths = $service->download([
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/classic-us/icons/56/inv_sword_39.jpg',
            'file_data_id' => 135349,
        ]);

        $this->assertEquals('blizzard/media/135349.jpg', $paths[135349]);
        $this->assertEquals(0, $httpCallCount);
        $this->assertEquals('existing-content', Storage::disk('public')->get($paths[135349]));
    }

    #[Test]
    public function download_assets_returns_null_on_http_failure(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('Not Found', 404),
        ]);

        $service = $this->makeService();
        $paths = $service->download([
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/classic-us/icons/56/inv_sword_39.jpg',
            'file_data_id' => 135349,
        ]);

        $this->assertNull($paths[135349]);
        Storage::disk('public')->assertMissing('blizzard/media/135349.jpg');
    }

    #[Test]
    public function download_assets_skips_invalid_assets(): void
    {
        Storage::fake('public');

        $service = $this->makeService();
        $paths = $service->download(['key' => 'icon']);

        $this->assertIsArray($paths);
        $this->assertEmpty($paths);
    }

    #[Test]
    public function download_assets_processes_multiple_assets(): void
    {
        Storage::fake('public');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('fake-image-content', 200),
        ]);

        $service = $this->makeService();
        $results = $service->download([
            [
                'key' => 'icon',
                'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword_39.jpg',
                'file_data_id' => 135349,
            ],
            [
                'key' => 'thumbnail',
                'value' => 'https://render.worldofwarcraft.com/icons/32/inv_sword_39.jpg',
                'file_data_id' => 135350,
            ],
        ]);

        $this->assertCount(2, $results);
        $this->assertEquals('blizzard/media/135349.jpg', $results[135349]);
        $this->assertEquals('blizzard/media/135350.jpg', $results[135350]);
        Storage::disk('public')->assertExists('blizzard/media/135349.jpg');
        Storage::disk('public')->assertExists('blizzard/media/135350.jpg');
    }

    // ==================== File Extension Handling ====================

    #[Test]
    public function download_handles_png_extension(): void
    {
        Storage::fake('public');

        Http::fake([
            '*' => Http::response('fake-image-content', 200),
        ]);

        $service = $this->makeService();
        $paths = $service->download([
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/icons/56/inv_sword_39.png',
            'file_data_id' => 111111,
        ]);

        $this->assertEquals('blizzard/media/111111.png', $paths[111111]);
    }

    // ==================== Filesystem Configuration ====================

    #[Test]
    public function download_uses_configured_filesystem(): void
    {
        Storage::fake('custom_disk');

        Http::fake([
            'render.worldofwarcraft.com/*' => Http::response('fake-image-content', 200),
        ]);

        $service = $this->makeService('eu', 'custom_disk');
        $service->download([
            'key' => 'icon',
            'value' => 'https://render.worldofwarcraft.com/classic-us/icons/56/inv_sword_39.jpg',
            'file_data_id' => 135349,
        ]);

        Storage::disk('custom_disk')->assertExists('blizzard/media/135349.jpg');
    }
}
