<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PlayableClassResource;
use App\Models\PlayableClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayableClassResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $playableClass = PlayableClass::factory()->create();

        $array = (new PlayableClassResource($playableClass))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('icon_url', $array);
    }

    #[Test]
    public function it_returns_correct_id(): void
    {
        $playableClass = PlayableClass::factory()->create();

        $array = (new PlayableClassResource($playableClass))->toArray(new Request);

        $this->assertSame($playableClass->id, $array['id']);
    }

    #[Test]
    public function it_returns_correct_name(): void
    {
        $playableClass = PlayableClass::factory()->create(['name' => 'Warrior']);

        $array = (new PlayableClassResource($playableClass))->toArray(new Request);

        $this->assertSame('Warrior', $array['name']);
    }

    #[Test]
    public function it_returns_correct_slug(): void
    {
        $playableClass = PlayableClass::factory()->create(['name' => 'Death Knight']);

        $array = (new PlayableClassResource($playableClass))->toArray(new Request);

        $this->assertSame('death-knight', $array['slug']);
    }

    #[Test]
    public function it_returns_null_icon_url_when_no_media_attached(): void
    {
        $playableClass = PlayableClass::factory()->create();

        $array = (new PlayableClassResource($playableClass))->toArray(new Request);

        $this->assertNull($array['icon_url']);
    }

    #[Test]
    public function it_does_not_expose_extra_keys(): void
    {
        $playableClass = PlayableClass::factory()->create();

        $array = (new PlayableClassResource($playableClass))->toArray(new Request);

        $this->assertCount(4, $array);
    }
}
