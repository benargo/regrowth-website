<?php

namespace Tests\Feature\Database\Seeders;

use App\Models\PlayableClass;
use App\Services\Blizzard\BlizzardService;
use Database\Seeders\PlayableClassSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\Downloaders\HttpFacadeDownloader;
use Tests\TestCase;

class PlayableClassSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function makeClassesResponse(array $classes = []): array
    {
        return ['classes' => $classes ?: [
            ['id' => 7, 'name' => 'Shaman'],
            ['id' => 11, 'name' => 'Druid'],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeMediaResponse(int $fileDataId = 12345, string $url = 'https://example.com/shaman.jpg'): array
    {
        return [
            'assets' => [
                ['key' => 'icon', 'value' => $url, 'file_data_id' => $fileDataId],
            ],
        ];
    }

    private function mockBlizzardService(?callable $callback = null): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) use ($callback) {
            $mock->shouldReceive('getPlayableClasses')
                ->andReturnUsing(fn () => $this->makeClassesResponse());
            $mock->shouldReceive('getPlayableClassMedia')
                ->andReturnUsing(fn (int $id) => $this->makeMediaResponse($id * 100, "https://example.com/class-{$id}.jpg"));

            if ($callback) {
                $callback($mock);
            }
        });
    }

    private function runSeeder(): void
    {
        Model::unguarded(fn () => app(PlayableClassSeeder::class)->run());
    }

    #[Test]
    public function seeder_creates_playable_classes_from_api(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-image-data', 200)]);
        config(['media-library.media_downloader' => HttpFacadeDownloader::class]);

        $this->mockBlizzardService();

        $this->runSeeder();

        $this->assertDatabaseCount('playable_classes', 2);
        $this->assertDatabaseHas('playable_classes', ['id' => 7, 'name' => 'Shaman']);
        $this->assertDatabaseHas('playable_classes', ['id' => 11, 'name' => 'Druid']);
        $this->assertDatabaseCount('media', 2);
        $this->assertDatabaseHas('media', ['model_type' => PlayableClass::class, 'collection_name' => 'blizzard_icons']);
    }

    #[Test]
    public function seeder_attaches_media_to_blizzard_icons_collection(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-image-data', 200)]);
        config(['media-library.media_downloader' => HttpFacadeDownloader::class]);

        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getPlayableClasses')
                ->andReturn(['classes' => [['id' => 7, 'name' => 'Shaman']]]);
            $mock->shouldReceive('getPlayableClassMedia')
                ->with(7)
                ->andReturn($this->makeMediaResponse(700, 'https://example.com/shaman.jpg'));
        });

        $this->runSeeder();

        $this->assertDatabaseCount('media', 1);
        $this->assertDatabaseHas('media', [
            'model_type' => PlayableClass::class,
            'collection_name' => 'blizzard_icons',
        ]);
    }

    #[Test]
    public function seeder_updates_existing_playable_class_without_duplicating(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-image-data', 200)]);
        config(['media-library.media_downloader' => HttpFacadeDownloader::class]);

        PlayableClass::factory()->create(['id' => 7, 'name' => 'Old Name']);

        $this->mockBlizzardService();

        $this->runSeeder();

        $this->assertDatabaseCount('playable_classes', 2);
        $this->assertDatabaseHas('playable_classes', ['id' => 7, 'name' => 'Shaman']);
    }

    #[Test]
    public function seeder_uses_default_icon_when_assets_are_empty(): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getPlayableClasses')
                ->andReturn(['classes' => [['id' => 7, 'name' => 'Shaman']]]);
            $mock->shouldReceive('getPlayableClassMedia')
                ->with(7)
                ->andReturn(['assets' => []]);
        });

        $this->runSeeder();

        $this->assertDatabaseCount('media', 0);
    }

    #[Test]
    public function seeder_uses_default_icon_when_media_response_has_no_assets_key(): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getPlayableClasses')
                ->andReturn(['classes' => [['id' => 7, 'name' => 'Shaman']]]);
            $mock->shouldReceive('getPlayableClassMedia')
                ->with(7)
                ->andReturn([]);
        });

        $this->runSeeder();

        $this->assertDatabaseCount('media', 0);
    }

    #[Test]
    public function seeder_does_nothing_when_classes_list_is_empty(): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('getPlayableClasses')
                ->andReturn(['classes' => []]);
            $mock->shouldNotReceive('getPlayableClassMedia');
        });

        $this->runSeeder();

        $this->assertDatabaseCount('playable_classes', 0);
    }
}
