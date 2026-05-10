<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\CharacterResource;
use App\Http\Resources\PlannedAbsenceResource;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\PlannedAbsence;
use App\Models\PlayableClass;
use App\Models\Raids\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
        $this->assertArrayHasKey('planned_absences', $array);
        $this->assertArrayHasKey('playable_class', $array);
        $this->assertArrayHasKey('playable_race', $array);
        $this->assertArrayHasKey('pivot', $array);
        $this->assertArrayHasKey('rank', $array);
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
    }

    #[Test]
    public function it_omits_playable_class_when_not_loaded(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['playable_class']);
    }

    #[Test]
    public function it_includes_playable_class_attributes_when_loaded(): void
    {
        $playableClass = PlayableClass::factory()->create(['name' => 'Warrior']);
        $character = Character::factory()->withPlayableClass($playableClass)->create();

        $array = (new CharacterResource($character->load('playableClass')))->toArray(new Request);

        $this->assertSame($playableClass->id, $array['playable_class']['id']);
        $this->assertSame('Warrior', $array['playable_class']['name']);
        $this->assertSame('warrior', $array['playable_class']['slug']);
        $this->assertArrayHasKey('icon_url', $array['playable_class']);
        $this->assertNull($array['playable_class']['icon_url']);
    }

    #[Test]
    public function it_omits_planned_absences_when_not_loaded(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['planned_absences']);
    }

    #[Test]
    public function it_includes_planned_absences_as_resource_collection_when_loaded(): void
    {
        $character = Character::factory()->create();
        PlannedAbsence::factory()->count(2)->create(['character_id' => $character->id]);

        $array = (new CharacterResource($character->load('plannedAbsences')))->toArray(new Request);

        $this->assertInstanceOf(AnonymousResourceCollection::class, $array['planned_absences']);
        $this->assertCount(2, $array['planned_absences']);
        $this->assertContainsOnlyInstancesOf(PlannedAbsenceResource::class, $array['planned_absences']);
    }

    #[Test]
    public function it_omits_pivot_when_not_from_pivot_table(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['pivot']);
    }

    #[Test]
    public function it_omits_rank_when_not_loaded(): void
    {
        $character = Character::factory()->create();

        $array = (new CharacterResource($character))->toArray(new Request);

        $this->assertInstanceOf(MissingValue::class, $array['rank']);
    }

    #[Test]
    public function it_includes_guild_rank_attributes_when_loaded(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->for($rank, 'rank')->create();

        $array = (new CharacterResource($character->load('rank')))->toArray(new Request);

        $this->assertIsArray($array['rank']);
        $this->assertArrayHasKey('id', $array['rank']);
        $this->assertSame($rank->id, $array['rank']['id']);
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

        $this->assertSame(['presence' => 3, 'is_loot_councillor' => false], $array['pivot']);
    }
}
