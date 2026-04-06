<?php

namespace Tests\Unit\Http\Resources\LootCouncil;

use App\Http\Resources\LootCouncil\PriorityResource;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\Priority;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\MediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PriorityResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockServices();
    }

    protected function mockServices(?string $iconUrl = null): void
    {
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('findMedia')->andReturn([
                'assets' => [
                    ['key' => 'icon', 'value' => 'https://example.com/icon.jpg', 'file_data_id' => 12345],
                ],
            ]);
        });

        $this->mock(MediaService::class, function (MockInterface $mock) use ($iconUrl) {
            $mock->shouldReceive('get')
                ->andReturnUsing(function ($media) use ($iconUrl) {
                    if (is_string($media)) {
                        return $iconUrl ?? "https://example.com/icons/{$media}.jpg";
                    }

                    return [12345 => $iconUrl ?? 'https://example.com/stored-icon.jpg'];
                });
        });
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
        $this->mockServices('https://example.com/spell-icon.jpg');

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
        $this->mockServices('https://example.com/icons/spell_holy_holybolt.jpg');

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
        $this->mock(BlizzardService::class, function (MockInterface $mock) {
            $mock->shouldReceive('findMedia')->andThrow(new \Exception('API Error'));
        });

        $this->mock(MediaService::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')->andThrow(new \Exception('API Error'));
        });

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
