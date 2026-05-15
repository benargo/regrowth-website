<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\TargetMarkerResource;
use App\Models\TargetMarker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TargetMarkerResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $marker = TargetMarker::factory()->create();

        $array = (new TargetMarkerResource($marker))->toArray(new Request);

        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('name', $array);
    }

    #[Test]
    public function it_returns_slug(): void
    {
        $marker = TargetMarker::factory()->create(['slug' => 'skull', 'name' => 'Skull']);

        $array = (new TargetMarkerResource($marker))->toArray(new Request);

        $this->assertSame('skull', $array['slug']);
    }

    #[Test]
    public function it_returns_name(): void
    {
        $marker = TargetMarker::factory()->create(['slug' => 'skull', 'name' => 'Skull']);

        $array = (new TargetMarkerResource($marker))->toArray(new Request);

        $this->assertSame('Skull', $array['name']);
    }

    #[Test]
    public function it_does_not_expose_extra_keys(): void
    {
        $marker = TargetMarker::factory()->create();

        $array = (new TargetMarkerResource($marker))->toArray(new Request);

        $this->assertCount(2, $array);
    }
}
