<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\CharacterSummaryResource;
use App\Models\Character;
use App\Models\GuildRank;
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
        $this->assertArrayHasKey('rank', $array);
        $this->assertArrayHasKey('playable_class', $array);
    }

    #[Test]
    public function it_returns_correct_scalar_values(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterSummaryResource($character))->toArray(new Request);

        $this->assertEquals($character->id, $array['id']);
        $this->assertEquals($character->name, $array['name']);
        $this->assertIsArray($array['playable_class']);
        $this->assertArrayHasKey('name', $array['playable_class']);
        $this->assertArrayHasKey('slug', $array['playable_class']);
        $this->assertArrayHasKey('icon_url', $array['playable_class']);
    }

    #[Test]
    public function it_returns_playable_class_when_set(): void
    {
        $character = Character::factory()->withPlayableClass(1, 'Warrior')->create();

        $array = (new CharacterSummaryResource($character))->toArray(new Request);

        $this->assertNotNull($array['playable_class']);
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
        $this->assertInstanceOf(GuildRank::class, $array['rank']);
    }
}
