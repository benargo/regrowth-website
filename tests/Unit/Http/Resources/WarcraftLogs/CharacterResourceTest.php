<?php

namespace Tests\Unit\Http\Resources\WarcraftLogs;

use App\Http\Resources\WarcraftLogs\CharacterResource;
use App\Models\Character;
use App\Models\WarcraftLogs\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\MissingValue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('is_main', $array);
        $this->assertArrayHasKey('is_loot_councillor', $array);
        $this->assertArrayHasKey('reached_level_cap_at', $array);
        $this->assertArrayHasKey('playable_class', $array);
        $this->assertArrayHasKey('playable_race', $array);
        $this->assertArrayHasKey('pivot', $array);
    }

    #[Test]
    public function it_returns_correct_scalar_fields(): void
    {
        $character = Character::factory()->main()->create(['name' => 'Arthas']);

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertSame($character->id, $array['id']);
        $this->assertSame('Arthas', $array['name']);
        $this->assertTrue($array['is_main']);
        $this->assertFalse($array['is_loot_councillor']);
        $this->assertNull($array['reached_level_cap_at']);
    }

    #[Test]
    public function it_omits_pivot_when_not_from_pivot_table(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['pivot']);
    }

    #[Test]
    public function it_includes_presence_pivot_when_loaded_via_report(): void
    {
        $report = Report::factory()->withoutGuildTag()->create();
        $character = Character::factory()->create();
        $report->characters()->attach($character, ['presence' => 3]);

        $loadedReport = $report->load('characters');
        $loadedCharacter = $loadedReport->characters->first();

        $array = (new CharacterResource($loadedCharacter))->toArray(new Request);

        $this->assertSame(['presence' => 3], $array['pivot']);
    }
}
