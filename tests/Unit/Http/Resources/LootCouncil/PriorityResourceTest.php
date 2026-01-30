<?php

namespace Tests\Unit\Http\Resources\LootCouncil;

use App\Http\Resources\LootCouncil\PriorityResource;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
use App\Services\Blizzard\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PriorityResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockMediaService();
    }

    protected function mockMediaService(?string $iconUrl = null): void
    {
        $mediaService = Mockery::mock(MediaService::class);
        $mediaService->shouldReceive('find')->andReturn([
            'assets' => [
                ['key' => 'icon', 'value' => 'https://example.com/icon.jpg', 'file_data_id' => 12345],
            ],
        ]);
        $mediaService->shouldReceive('getAssetUrls')
            ->andReturn([12345 => $iconUrl ?? 'https://example.com/stored-icon.jpg']);
        $mediaService->shouldReceive('getIconUrlByName')
            ->andReturnUsing(fn ($name) => $iconUrl ?? "https://example.com/icons/{$name}.jpg");

        $this->app->instance(MediaService::class, $mediaService);
    }

    #[Test]
    public function it_returns_id(): void
    {
        $priority = Priority::factory()->create();

        $resource = new PriorityResource($priority);
        $array = $resource->toArray(new Request);

        $this->assertSame($priority->id, $array['id']);
    }

    #[Test]
    public function it_returns_title(): void
    {
        $priority = Priority::factory()->create(['title' => 'Tank']);

        $resource = new PriorityResource($priority);
        $array = $resource->toArray(new Request);

        $this->assertSame('Tank', $array['title']);
    }

    #[Test]
    public function it_returns_type_role(): void
    {
        $priority = Priority::factory()->role()->create();

        $resource = new PriorityResource($priority);
        $array = $resource->toArray(new Request);

        $this->assertSame('role', $array['type']);
    }

    #[Test]
    public function it_returns_type_class(): void
    {
        $priority = Priority::factory()->classType()->create();

        $resource = new PriorityResource($priority);
        $array = $resource->toArray(new Request);

        $this->assertSame('class', $array['type']);
    }

    #[Test]
    public function it_returns_type_spec(): void
    {
        $priority = Priority::factory()->spec()->create();

        $resource = new PriorityResource($priority);
        $array = $resource->toArray(new Request);

        $this->assertSame('spec', $array['type']);
    }

    #[Test]
    public function it_returns_media_url_from_media_type_and_id(): void
    {
        $this->mockMediaService('https://example.com/spell-icon.jpg');

        $priority = Priority::factory()->create([
            'media' => [
                'media_type' => 'spell',
                'media_id' => 12345,
            ],
        ]);

        $resource = new PriorityResource($priority);
        $array = $resource->toArray(new Request);

        $this->assertSame('https://example.com/spell-icon.jpg', $array['media']);
    }

    #[Test]
    public function it_returns_media_url_from_media_name(): void
    {
        $this->mockMediaService('https://example.com/icons/spell_holy_holybolt.jpg');

        $priority = Priority::factory()->create([
            'media' => [
                'media_name' => 'spell_holy_holybolt',
            ],
        ]);

        $resource = new PriorityResource($priority);
        $array = $resource->toArray(new Request);

        $this->assertSame('https://example.com/icons/spell_holy_holybolt.jpg', $array['media']);
    }

    #[Test]
    public function it_returns_null_media_when_media_is_empty_array(): void
    {
        $priority = Priority::factory()->create(['media' => []]);

        $resource = new PriorityResource($priority);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['media']);
    }

    #[Test]
    public function it_returns_null_media_when_api_fails(): void
    {
        $mediaService = Mockery::mock(MediaService::class);
        $mediaService->shouldReceive('find')->andThrow(new \Exception('API Error'));
        $mediaService->shouldReceive('getIconUrlByName')->andThrow(new \Exception('API Error'));

        $this->app->instance(MediaService::class, $mediaService);

        $priority = Priority::factory()->create([
            'media' => [
                'media_type' => 'spell',
                'media_id' => 12345,
            ],
        ]);

        $resource = new PriorityResource($priority);
        $array = $resource->toArray(new Request);

        $this->assertNull($array['media']);
    }

    #[Test]
    public function it_returns_weight_when_pivot_is_loaded(): void
    {
        $item = Item::factory()->create();
        $priority = Priority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 75]);

        // Reload priority through the item to get pivot data
        $priorityWithPivot = $item->priorities()->first();

        $resource = new PriorityResource($priorityWithPivot);
        $array = $resource->toArray(new Request);

        $this->assertSame(75, $array['weight']);
    }

    #[Test]
    public function it_excludes_weight_when_pivot_is_not_loaded(): void
    {
        $priority = Priority::factory()->create();

        $resource = new PriorityResource($priority);
        $array = $resource->resolve(new Request);

        $this->assertArrayNotHasKey('weight', $array);
    }

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $priority = Priority::factory()->create();

        $resource = new PriorityResource($priority);
        $array = $resource->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('media', $array);
    }
}
