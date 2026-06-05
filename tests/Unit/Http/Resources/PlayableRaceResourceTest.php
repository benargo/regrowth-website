<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PlayableRaceResource;
use App\Models\Character;
use App\Models\PlayableRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayableRaceResourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_all_expected_keys(): void
    {
        $playableRace = PlayableRace::factory()->create();

        $array = (new PlayableRaceResource($playableRace))->resolve(new Request);

        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
    }

    #[Test]
    public function it_returns_correct_id(): void
    {
        $playableRace = PlayableRace::factory()->create();

        $array = (new PlayableRaceResource($playableRace))->resolve(new Request);

        $this->assertSame($playableRace->id, $array['id']);
    }

    #[Test]
    public function it_returns_correct_name(): void
    {
        $playableRace = PlayableRace::factory()->create(['name' => 'Draenei']);

        $array = (new PlayableRaceResource($playableRace))->resolve(new Request);

        $this->assertSame('Draenei', $array['name']);
    }

    #[Test]
    public function it_does_not_expose_extra_keys(): void
    {
        $playableRace = PlayableRace::factory()->create();

        $array = (new PlayableRaceResource($playableRace))->resolve(new Request);

        $this->assertCount(2, $array);
    }

    #[Test]
    public function it_omits_characters_when_not_loaded(): void
    {
        $playableRace = PlayableRace::factory()->create();

        $array = (new PlayableRaceResource($playableRace))->resolve(new Request);

        $this->assertArrayNotHasKey('characters', $array);
    }

    #[Test]
    public function it_includes_characters_when_loaded(): void
    {
        $playableRace = PlayableRace::factory()->create();
        Character::factory()->count(2)->for($playableRace, 'playableRace')->create();

        $playableRace->load('characters');

        $array = (new PlayableRaceResource($playableRace))->resolve(new Request);

        $this->assertArrayHasKey('characters', $array);
        $this->assertCount(2, $array['characters']);
    }
}
