<?php

namespace Tests\Feature\Actions\GameVersion;

use App\Actions\GameVersion\FindOrImportPlayableClass;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassMediaRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassRequest;
use App\Jobs\AttachBlizzardIconToModel;
use App\Models\PlayableClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\Blizzard\MocksBlizzardServices;
use Tests\TestCase;

#[Group('characters')]
#[Group('blizzard-integration')]
class FindOrImportPlayableClassTest extends TestCase
{
    use MocksBlizzardServices;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Storage::fake('s3');
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    private function assets(): array
    {
        return [
            ['key' => 'icon', 'value' => 'https://render.worldofwarcraft.com/eu/icons/56/class_priest.jpg'],
            ['key' => 'banner', 'value' => 'https://render.worldofwarcraft.com/eu/icons/56/class_priest_banner.jpg'],
        ];
    }

    #[Group('happy-path')]
    #[Test]
    public function it_returns_an_existing_class_without_calling_blizzard(): void
    {
        $class = PlayableClass::factory()->create(['id' => 5, 'name' => 'Heiler']);
        $class->addMediaFromString('BINARY')->usingFileName('class.jpg')->toMediaCollection('blizzard_icons');
        $this->mockGetPlayableClass();
        $this->mockGetPlayableClassMedia();
        $this->applyBlizzardMocks();

        $result = FindOrImportPlayableClass::run(5, BlizzardNamespace::FOREVER);

        $this->assertSame('Heiler', $result->name);
        Saloon::assertNotSent(GetPlayableClassRequest::class);
        Saloon::assertNotSent(GetPlayableClassMediaRequest::class);
    }

    #[Group('edge-case')]
    #[Test]
    public function it_queues_icons_for_an_existing_class_with_no_icons(): void
    {
        PlayableClass::factory()->create(['id' => 5]);
        $this->mockGetPlayableClass();
        $this->mockGetPlayableClassMedia($this->assets());
        $this->applyBlizzardMocks();

        FindOrImportPlayableClass::run(5, BlizzardNamespace::FOREVER);

        Queue::assertPushed(AttachBlizzardIconToModel::class, 2);
        Saloon::assertNotSent(GetPlayableClassRequest::class);
    }

    #[Group('edge-case')]
    #[Test]
    public function it_queues_icons_on_a_later_call_after_a_failed_media_request(): void
    {
        $this->mockGetPlayableClass();
        $this->mockGetPlayableClassMedia(status: 500);
        $this->applyBlizzardMocks();

        FindOrImportPlayableClass::run(5, BlizzardNamespace::FOREVER);

        Queue::assertNothingPushed();

        $this->mockGetPlayableClassMedia($this->assets());
        $this->applyBlizzardMocks();

        FindOrImportPlayableClass::run(5, BlizzardNamespace::FOREVER);

        Queue::assertPushed(AttachBlizzardIconToModel::class, 2);
    }

    #[Group('edge-case')]
    #[Test]
    public function it_returns_an_existing_class_when_its_icon_repair_fails(): void
    {
        PlayableClass::factory()->create(['id' => 5, 'name' => 'Heiler']);
        $this->mockGetPlayableClass();
        $this->mockGetPlayableClassMedia(status: 500);
        $this->applyBlizzardMocks();

        $result = FindOrImportPlayableClass::run(5, BlizzardNamespace::FOREVER);

        $this->assertSame('Heiler', $result->name);
        Queue::assertNothingPushed();
    }

    #[Group('happy-path')]
    #[Test]
    public function it_creates_a_missing_class_from_the_given_namespace(): void
    {
        $this->mockGetPlayableClass();
        $this->mockGetPlayableClassMedia();
        $this->applyBlizzardMocks();

        $result = FindOrImportPlayableClass::run(5, BlizzardNamespace::FOREVER);

        $this->assertSame(5, $result->id);
        $this->assertDatabaseHas('playable_classes', ['id' => 5, 'name' => 'Priest']);
        Saloon::assertSent(
            fn ($request, $response) => $request instanceof GetPlayableClassRequest
                && $response->getPendingRequest()->headers()->get('Battlenet-Namespace') === 'static-forever-eu',
        );
    }

    #[Group('happy-path')]
    #[Test]
    public function it_queues_an_icon_for_each_media_asset(): void
    {
        $this->mockGetPlayableClass();
        $this->mockGetPlayableClassMedia($this->assets());
        $this->applyBlizzardMocks();

        FindOrImportPlayableClass::run(5, BlizzardNamespace::FOREVER);

        Queue::assertPushed(AttachBlizzardIconToModel::class, 2);
    }

    #[Group('edge-case')]
    #[Test]
    public function it_keeps_the_class_when_its_media_request_fails(): void
    {
        $this->mockGetPlayableClass();
        $this->mockGetPlayableClassMedia(status: 500);
        $this->applyBlizzardMocks();

        $result = FindOrImportPlayableClass::run(5, BlizzardNamespace::FOREVER);

        $this->assertSame(5, $result->id);
        $this->assertDatabaseHas('playable_classes', ['id' => 5]);
        Queue::assertNothingPushed();
    }

    #[Group('edge-case')]
    #[Test]
    public function it_keeps_the_class_when_the_media_response_has_no_assets_key(): void
    {
        $this->mockGetPlayableClass();
        $this->mockGetPlayableClassMedia();
        $this->pendingBlizzardMocks[GetPlayableClassMediaRequest::class] = MockResponse::make(body: ['id' => 5], status: 200);
        $this->applyBlizzardMocks();

        $result = FindOrImportPlayableClass::run(5, BlizzardNamespace::FOREVER);

        $this->assertSame(5, $result->id);
        $this->assertDatabaseHas('playable_classes', ['id' => 5]);
        Queue::assertNothingPushed();
    }

    #[Group('edge-case')]
    #[Test]
    public function it_throws_when_blizzard_cannot_return_the_class(): void
    {
        $this->mockGetPlayableClass(status: 500);
        $this->mockGetPlayableClassMedia();
        $this->applyBlizzardMocks();

        try {
            FindOrImportPlayableClass::run(5, BlizzardNamespace::FOREVER);
            $this->fail('Expected a BlizzardRequestException.');
        } catch (BlizzardRequestException) {
            $this->assertDatabaseMissing('playable_classes', ['id' => 5]);
        }
    }
}
