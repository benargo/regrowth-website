<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\AffectType;
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
        $this->assertArrayHasKey('color', $array);
        $this->assertArrayHasKey('icon', $array);
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
    public function it_returns_color(): void
    {
        $spell = Spell::factory()->create(['type' => AffectType::Physical]);

        $array = (new SpellResource($spell))->toArray(new Request);

        $this->assertSame('affect-physical', $array['color']);
    }

    #[Test]
    public function it_returns_icon(): void
    {
        $spell = Spell::factory()->create();

        $array = (new SpellResource($spell))->toArray(new Request);

        $this->assertArrayHasKey('icon', $array);
        $this->assertNull($array['icon']);
    }

    #[Test]
    public function it_does_not_expose_extra_keys(): void
    {
        $spell = Spell::factory()->create();

        $array = (new SpellResource($spell))->toArray(new Request);

        $this->assertCount(4, $array);
    }
}
