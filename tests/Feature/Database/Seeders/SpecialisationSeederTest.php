<?php

namespace Tests\Feature\Database\Seeders;

use App\Enums\CharacterRole;
use App\Models\CharacterSpecialisation;
use App\Models\PlayableClass;
use App\Services\Blizzard\MediaService;
use Database\Seeders\PlayableClassSeeder;
use Database\Seeders\SpecialisationSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\Downloaders\HttpFacadeDownloader;
use Tests\TestCase;

class SpecialisationSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Stub PlayableClassSeeder so it doesn't require Blizzard API calls;
        // we seed the PlayableClasses we need directly via factory.
        $this->mock(PlayableClassSeeder::class, function (MockInterface $mock) {
            $mock->shouldReceive('setContainer')->andReturnSelf();
            $mock->shouldReceive('__invoke')->andReturnNull();
        });
    }

    private function seedPlayableClasses(): void
    {
        Model::unguarded(function () {
            PlayableClass::factory()->create(['name' => 'Druid']);
            PlayableClass::factory()->create(['name' => 'Hunter']);
            PlayableClass::factory()->create(['name' => 'Mage']);
            PlayableClass::factory()->create(['name' => 'Paladin']);
            PlayableClass::factory()->create(['name' => 'Priest']);
            PlayableClass::factory()->create(['name' => 'Rogue']);
            PlayableClass::factory()->create(['name' => 'Shaman']);
            PlayableClass::factory()->create(['name' => 'Warlock']);
            PlayableClass::factory()->create(['name' => 'Warrior']);
        });
    }

    private function mockMediaService(?callable $callback = null): void
    {
        $this->mock(MediaService::class, function (MockInterface $mock) use ($callback) {
            $mock->shouldReceive('get')
                ->andReturnUsing(fn (string $icon) => "https://example.com/icons/{$icon}.jpg");

            if ($callback) {
                $callback($mock);
            }
        });
    }

    private function runSeeder(): void
    {
        Model::unguarded(function () {
            $seeder = app(SpecialisationSeeder::class);
            $seeder->setContainer(app());
            $seeder->run();
        });
    }

    // ==================== Record Creation ====================

    #[Test]
    public function seeder_creates_all_27_specialisations(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-image-data', 200)]);
        config(['media-library.media_downloader' => HttpFacadeDownloader::class]);

        $this->mockMediaService();
        $this->seedPlayableClasses();

        $this->runSeeder();

        $this->assertDatabaseCount('character_specialisations', 27);
    }

    #[Test]
    public function seeder_creates_specialisations_with_correct_roles(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-image-data', 200)]);
        config(['media-library.media_downloader' => HttpFacadeDownloader::class]);

        $this->mockMediaService();
        $this->seedPlayableClasses();

        $this->runSeeder();

        $druid = PlayableClass::where('name', 'Druid')->first();

        $this->assertDatabaseHas('character_specialisations', [
            'playable_class_id' => $druid->id,
            'name' => 'Balance',
            'role' => CharacterRole::damage->value,
        ]);

        $this->assertDatabaseHas('character_specialisations', [
            'playable_class_id' => $druid->id,
            'name' => 'Restoration',
            'role' => CharacterRole::healer->value,
        ]);

        $warrior = PlayableClass::where('name', 'Warrior')->first();

        $this->assertDatabaseHas('character_specialisations', [
            'playable_class_id' => $warrior->id,
            'name' => 'Protection',
            'role' => CharacterRole::tank->value,
        ]);
    }

    #[Test]
    public function seeder_attaches_icon_to_blizzard_icons_media_collection(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-image-data', 200)]);
        config(['media-library.media_downloader' => HttpFacadeDownloader::class]);

        $this->mockMediaService();
        $this->seedPlayableClasses();

        $this->runSeeder();

        $this->assertDatabaseCount('media', 27);
        $this->assertDatabaseHas('media', [
            'model_type' => CharacterSpecialisation::class,
            'collection_name' => 'blizzard_icons',
        ]);
    }

    // ==================== Idempotency ====================

    #[Test]
    public function seeder_is_idempotent_and_does_not_create_duplicate_specialisations(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-image-data', 200)]);
        config(['media-library.media_downloader' => HttpFacadeDownloader::class]);

        $this->mockMediaService();
        $this->seedPlayableClasses();

        $this->runSeeder();
        $this->runSeeder();

        $this->assertDatabaseCount('character_specialisations', 27);
    }

    #[Test]
    public function seeder_replaces_existing_icon_without_duplicating_media(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-image-data', 200)]);
        config(['media-library.media_downloader' => HttpFacadeDownloader::class]);

        $this->mockMediaService();
        $this->seedPlayableClasses();

        $this->runSeeder();
        $this->runSeeder();

        $this->assertDatabaseCount('media', 27);
    }

    // ==================== Media Service Integration ====================

    #[Test]
    public function seeder_passes_icon_name_to_media_service(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-image-data', 200)]);
        config(['media-library.media_downloader' => HttpFacadeDownloader::class]);

        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->with('spell_nature_starfall')
                ->once()
                ->andReturn('https://example.com/icons/spell_nature_starfall.jpg');

            $mock->shouldReceive('get')
                ->andReturnUsing(fn (string $icon) => "https://example.com/icons/{$icon}.jpg");
        });

        $this->seedPlayableClasses();

        $this->runSeeder();
    }

    #[Test]
    public function seeder_skips_media_attachment_when_media_service_returns_null(): void
    {
        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')->andReturnNull();
        });

        $this->seedPlayableClasses();

        $this->runSeeder();

        $this->assertDatabaseCount('character_specialisations', 27);
        $this->assertDatabaseCount('media', 0);
    }

    // ==================== Class Association ====================

    #[Test]
    public function seeder_associates_specialisations_with_correct_playable_class(): void
    {
        Storage::fake('public');
        Http::fake(['*' => Http::response('fake-image-data', 200)]);
        config(['media-library.media_downloader' => HttpFacadeDownloader::class]);

        $this->mockMediaService();
        $this->seedPlayableClasses();

        $this->runSeeder();

        $shaman = PlayableClass::where('name', 'Shaman')->first();

        $shamanSpecs = CharacterSpecialisation::where('playable_class_id', $shaman->id)
            ->pluck('name')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['Elemental', 'Enhancement', 'Restoration'], $shamanSpecs);
    }
}
