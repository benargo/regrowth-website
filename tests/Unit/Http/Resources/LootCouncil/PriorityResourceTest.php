<?php

namespace Tests\Unit\Http\Resources\LootCouncil;

use App\Http\Resources\LootCouncil\PriorityResource;
use App\Models\Item;
use App\Models\LootCouncil\Priority;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PriorityResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_id(): void
    {
        $priority = Priority::factory()->create();

        $array = (new PriorityResource($priority))->toArray(new Request);

        $this->assertSame($priority->id, $array['id']);
    }

    #[Test]
    public function it_returns_title(): void
    {
        $priority = Priority::factory()->create(['title' => 'Tank']);

        $array = (new PriorityResource($priority))->toArray(new Request);

        $this->assertSame('Tank', $array['title']);
    }

    #[Test]
    public function it_returns_type_role(): void
    {
        $priority = Priority::factory()->role()->create();

        $array = (new PriorityResource($priority))->toArray(new Request);

        $this->assertSame('role', $array['type']);
    }

    #[Test]
    public function it_returns_type_class(): void
    {
        $priority = Priority::factory()->classType()->create();

        $array = (new PriorityResource($priority))->toArray(new Request);

        $this->assertSame('class', $array['type']);
    }

    #[Test]
    public function it_returns_type_spec(): void
    {
        $priority = Priority::factory()->spec()->create();

        $array = (new PriorityResource($priority))->toArray(new Request);

        $this->assertSame('spec', $array['type']);
    }

    #[Test]
    public function it_returns_media_url_when_icon_is_attached(): void
    {
        $priority = Priority::factory()->create();
        $priority->addMediaFromString('BINARY')
            ->usingFileName('inv_shield_04.jpg')
            ->toMediaCollection('blizzard_icons');

        $array = (new PriorityResource($priority))->toArray(new Request);

        $this->assertNotNull($array['media']);
        $this->assertIsString($array['media']);
    }

    #[Test]
    public function it_returns_null_media_when_no_icon_is_attached(): void
    {
        $priority = Priority::factory()->create();

        $array = (new PriorityResource($priority))->toArray(new Request);

        $this->assertNull($array['media']);
    }

    #[Test]
    public function it_returns_weight_when_pivot_is_loaded(): void
    {
        $item = Item::factory()->create();
        $priority = Priority::factory()->create();
        $item->priorities()->attach($priority->id, ['weight' => 75]);

        $priorityWithPivot = $item->priorities()->first();

        $array = (new PriorityResource($priorityWithPivot))->toArray(new Request);

        $this->assertSame(75, $array['weight']);
    }

    #[Test]
    public function it_excludes_weight_when_pivot_is_not_loaded(): void
    {
        $priority = Priority::factory()->create();

        $array = (new PriorityResource($priority))->resolve(new Request);

        $this->assertArrayNotHasKey('weight', $array);
    }

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $priority = Priority::factory()->create();

        $array = (new PriorityResource($priority))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('title', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('media', $array);
    }
}
