<?php

namespace Tests\Feature\Database\Seeders;

use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassIndexRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassMediaRequest;
use App\Http\Integrations\Blizzard\Requests\Render\FetchAssetRequest;
use App\Models\PlayableClass;
use Database\Seeders\PlayableClassSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;
use Tests\TestCase;

class PlayableClassSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    /**
     * @return array<string, mixed>
     */
    private function makeClassesResponse(array $classes = []): array
    {
        return ['classes' => $classes ?: [
            ['key' => ['href' => 'https://example.test/class/7'], 'name' => 'Shaman', 'id' => 7],
            ['key' => ['href' => 'https://example.test/class/11'], 'name' => 'Druid', 'id' => 11],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeMediaResponse(int $classId): array
    {
        return [
            'id' => $classId,
            'assets' => [
                [
                    'key' => 'icon',
                    'value' => "https://render.worldofwarcraft.com/eu/icons/56/class-{$classId}.jpg",
                    'file_data_id' => $classId * 100,
                ],
            ],
        ];
    }

    private function fakeSaloon(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetPlayableClassIndexRequest::class => MockResponse::make(
                body: $this->makeClassesResponse(),
                status: 200,
            ),
            GetPlayableClassMediaRequest::class => function (PendingRequest $request): MockResponse {
                $classId = (int) last(explode('/', parse_url($request->getUrl(), PHP_URL_PATH)));

                return MockResponse::make(body: $this->makeMediaResponse($classId), status: 200);
            },
            FetchAssetRequest::class => MockResponse::make(body: 'fake-image-data', status: 200),
        ]);
    }

    private function runSeeder(): void
    {
        Model::unguarded(fn () => app(PlayableClassSeeder::class)->run());
    }

    #[Test]
    public function seeder_creates_playable_classes_from_api(): void
    {
        $this->fakeSaloon();

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
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetPlayableClassIndexRequest::class => MockResponse::make(
                body: ['classes' => [['key' => ['href' => 'https://example.test/class/7'], 'name' => 'Shaman', 'id' => 7]]],
                status: 200,
            ),
            GetPlayableClassMediaRequest::class => MockResponse::make(
                body: $this->makeMediaResponse(7),
                status: 200,
            ),
            FetchAssetRequest::class => MockResponse::make(body: 'fake-image-data', status: 200),
        ]);

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
        $this->fakeSaloon();

        PlayableClass::factory()->create(['id' => 7, 'name' => 'Old Name']);

        $this->runSeeder();

        $this->assertDatabaseCount('playable_classes', 2);
        $this->assertDatabaseHas('playable_classes', ['id' => 7, 'name' => 'Shaman']);
    }

    #[Test]
    public function seeder_uses_default_icon_when_assets_are_empty(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetPlayableClassIndexRequest::class => MockResponse::make(
                body: ['classes' => [['key' => ['href' => 'https://example.test/class/7'], 'name' => 'Shaman', 'id' => 7]]],
                status: 200,
            ),
            GetPlayableClassMediaRequest::class => MockResponse::make(
                body: ['id' => 7, 'assets' => []],
                status: 200,
            ),
        ]);

        $this->runSeeder();

        $this->assertDatabaseCount('media', 0);
    }

    #[Test]
    public function seeder_does_nothing_when_classes_list_is_empty(): void
    {
        Saloon::fake([
            'eu.battle.net/oauth/token' => MockResponse::make(
                body: ['access_token' => 'test_token', 'token_type' => 'bearer', 'expires_in' => 3600],
                status: 200,
            ),
            GetPlayableClassIndexRequest::class => MockResponse::make(
                body: ['classes' => []],
                status: 200,
            ),
        ]);

        $this->runSeeder();

        $this->assertDatabaseCount('playable_classes', 0);
    }
}
