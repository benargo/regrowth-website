<?php

namespace Tests\Feature\Actions\Boss;

use App\Actions\Boss\BuildBossFormOptions;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class BuildBossFormOptionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_groups_the_raid_options_by_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Anniversary']);
        $karazhan = Raid::factory()->for(Phase::factory()->for($gameVersion))->create(['name' => 'Karazhan', 'difficulty' => 'Normal']);
        $unowned = Raid::factory()->for(Phase::factory()->state(['game_version_id' => null]))->create(['name' => 'Naxxramas', 'difficulty' => 'Heroic']);

        $this->assertSame(['raids' => [
            ['label' => 'Anniversary', 'options' => [['value' => $karazhan->id, 'label' => 'Karazhan (Normal)']]],
            ['label' => 'No game version', 'options' => [['value' => $unowned->id, 'label' => 'Naxxramas (Heroic)']]],
        ]], BuildBossFormOptions::run());
    }

    #[Test]
    public function it_leaves_out_game_versions_with_no_raids(): void
    {
        GameVersion::factory()->create();

        $this->assertSame(['raids' => []], BuildBossFormOptions::run());
    }
}
