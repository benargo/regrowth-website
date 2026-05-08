<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\SpellResource;
use App\Models\Spell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SpellResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $spell = Spell::factory()->create();

        $array = (new SpellResource($spell))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('icon_url', $array);
    }

    #[Test]
    public function it_returns_id(): void
    {
        $spell = Spell::factory()->create();

        $array = (new SpellResource($spell))->toArray(new Request);

        $this->assertSame($spell->id, $array['id']);
    }

    #[Test]
    public function it_returns_name(): void
    {
        $spell = Spell::factory()->create(['name' => 'Fireball']);

        $array = (new SpellResource($spell))->toArray(new Request);

        $this->assertSame('Fireball', $array['name']);
    }

    #[Test]
    public function it_returns_icon_url(): void
    {
        $spell = Spell::factory()->create(['icon_url' => 'https://example.com/fireball.png']);

        $array = (new SpellResource($spell))->toArray(new Request);

        $this->assertSame('https://example.com/fireball.png', $array['icon_url']);
    }

    #[Test]
    public function it_returns_null_icon_url_when_not_set(): void
    {
        $spell = Spell::factory()->create(['icon_url' => null]);

        $array = (new SpellResource($spell))->toArray(new Request);

        $this->assertNull($array['icon_url']);
    }

    #[Test]
    public function it_does_not_expose_extra_keys(): void
    {
        $spell = Spell::factory()->create();

        $array = (new SpellResource($spell))->toArray(new Request);

        $this->assertCount(3, $array);
    }
}
