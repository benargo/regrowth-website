<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\CharacterSummaryResource;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterSummaryResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterSummaryResource($character))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('level', $array);
        $this->assertArrayHasKey('rank', $array);
        $this->assertArrayHasKey('playable_class', $array);
    }

    #[Test]
    public function it_returns_correct_scalar_values(): void
    {
        $character = Character::factory()->withPlayableClass()->create();
        $character->load('playableClass');

        $array = (new CharacterSummaryResource($character))->toArray(new Request);

        $this->assertEquals($character->id, $array['id']);
        $this->assertEquals($character->name, $array['name']);
        $this->assertEquals($character->level, $array['level']);
        $this->assertIsArray($array['playable_class']);
        $this->assertArrayHasKey('id', $array['playable_class']);
        $this->assertArrayHasKey('name', $array['playable_class']);
        $this->assertArrayHasKey('slug', $array['playable_class']);
        $this->assertArrayHasKey('icon_url', $array['playable_class']);
        $this->assertNull($array['playable_class']['icon_url']);
    }

    #[Test]
    public function it_returns_playable_class_when_loaded(): void
    {
        $character = Character::factory()->withPlayableClass()->create();
        $character->load('playableClass');

        $array = (new CharacterSummaryResource($character))->toArray(new Request);

        $this->assertIsArray($array['playable_class']);
        $this->assertNotNull($array['playable_class']);
    }

    #[Test]
    public function it_returns_missing_value_for_playable_class_when_not_loaded(): void
    {
        $character = Character::factory()->withPlayableClass()->create();

        $array = (new CharacterSummaryResource($character))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['playable_class']);
    }

    #[Test]
    public function it_returns_null_for_rank_when_not_loaded(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterSummaryResource($character))->toArray(new Request);

        $this->assertArrayHasKey('rank', $array);
        $this->assertInstanceOf(MissingValue::class, $array['rank']);
    }

    #[Test]
    public function it_includes_rank_when_loaded(): void
    {
        $character = Character::factory()->withRank()->create();
        $character->load('rank');

        $array = (new CharacterSummaryResource($character))->toArray(new Request);

        $this->assertArrayHasKey('rank', $array);
        $this->assertIsArray($array['rank']);
    }
}
