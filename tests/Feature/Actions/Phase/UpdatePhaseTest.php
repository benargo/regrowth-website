<?php

namespace Tests\Feature\Actions\Phase;

use App\Actions\Phase\UpdatePhase;
use App\Models\GameVersion;
use App\Models\Phase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class UpdatePhaseTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_updates_only_the_fields_it_is_given(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->for($gameVersion)->create(['number' => '2.0', 'description' => 'Old']);

        UpdatePhase::run($phase, ['description' => 'New']);

        $phase->refresh();
        $this->assertSame('New', $phase->description);
        $this->assertSame(2, $phase->number);
        $this->assertSame($gameVersion->id, $phase->game_version_id);
    }

    #[Test]
    public function it_clears_the_start_date_and_game_version_given_as_null(): void
    {
        $phase = Phase::factory()->for(GameVersion::factory())->started()->create();

        UpdatePhase::run($phase, ['start_date' => null, 'game_version_id' => null]);

        $phase->refresh();
        $this->assertNull($phase->start_date);
        $this->assertNull($phase->game_version_id);
    }
}
