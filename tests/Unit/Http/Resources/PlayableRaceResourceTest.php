<?php

namespace Tests\Unit\Http\Resources;

use App\Enums\Faction;
use App\Http\Resources\PlayableRaceResource;
use App\Models\PlayableRace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
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
        $this->assertArrayHasKey('faction', $array);
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
    public function it_returns_correct_faction(): void
    {
        $playableRace = PlayableRace::factory()->create(['faction' => Faction::ALLIANCE]);

        $array = (new PlayableRaceResource($playableRace))->resolve(new Request);

        $this->assertSame('Alliance', $array['faction']);
    }

    #[Test]
    public function it_does_not_expose_extra_keys(): void
    {
        $playableRace = PlayableRace::factory()->create();

        $array = (new PlayableRaceResource($playableRace))->resolve(new Request);

        $this->assertArrayNotHasKey('characters', $array);
        $this->assertSame(['id', 'name', 'faction'], array_keys($array));
    }
}
