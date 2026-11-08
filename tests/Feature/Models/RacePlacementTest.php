<?php

namespace Tests\Feature\Models;

use App\Enums\Medal;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\RacePlacement;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class RacePlacementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_derives_the_medal_from_the_position(): void
    {
        $this->assertSame(Medal::Gold, RacePlacement::factory()->make(['position' => 1])->medal);
        $this->assertNull(RacePlacement::factory()->make(['position' => 4])->medal);
    }

    #[Test]
    public function it_has_no_medal_without_a_position(): void
    {
        $this->assertNull((new RacePlacement)->medal);
    }

    #[Test]
    public function it_belongs_to_a_game_version(): void
    {
        $version = GameVersion::factory()->create();
        $placement = RacePlacement::factory()->for($version)->create();

        $this->assertTrue($placement->gameVersion->is($version));
    }

    #[Test]
    public function it_rejects_a_second_placement_for_the_same_character_and_version(): void
    {
        $placement = RacePlacement::factory()->create(['position' => 1]);

        $this->expectException(UniqueConstraintViolationException::class);

        RacePlacement::factory()->create([
            'game_version_id' => $placement->game_version_id,
            'character_id' => $placement->character_id,
            'position' => 2,
        ]);
    }

    #[Test]
    public function it_rejects_a_duplicate_position_in_the_same_version(): void
    {
        $placement = RacePlacement::factory()->create(['position' => 1]);

        $this->expectException(UniqueConstraintViolationException::class);

        RacePlacement::factory()->create([
            'game_version_id' => $placement->game_version_id,
            'position' => 1,
        ]);
    }

    #[Test]
    public function it_does_not_prune_placed_characters(): void
    {
        $version = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);
        $stale = now()->subDays(15);
        $placed = Character::factory()->create(['game_version_id' => $version->id, 'updated_at' => $stale]);
        $unplaced = Character::factory()->create(['game_version_id' => $version->id, 'updated_at' => $stale]);
        Character::query()->whereKey([$placed->id, $unplaced->id])->update(['updated_at' => $stale]);
        RacePlacement::factory()->create(['game_version_id' => $version->id, 'character_id' => $placed->id]);

        $ids = (new Character)->prunable()->pluck('id');

        $this->assertTrue($ids->contains($unplaced->id));
        $this->assertFalse($ids->contains($placed->id));
    }

    #[Test]
    public function it_finds_the_forever_game_version(): void
    {
        $this->assertNull(GameVersion::forever());

        GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::ERA]);
        $forever = GameVersion::factory()->create(['blizzard_namespace' => BlizzardNamespace::FOREVER]);

        $this->assertTrue(GameVersion::forever()->is($forever));
    }
}
