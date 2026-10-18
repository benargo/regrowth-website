<?php

namespace Tests\Feature\Actions\Raid;

use App\Actions\Raid\BuildRaidFormOptions;
use App\Enums\BannerPosition;
use App\Models\GameVersion;
use App\Models\Phase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class BuildRaidFormOptionsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_groups_the_phases_under_their_game_versions_newest_first(): void
    {
        $era = GameVersion::factory()->create(['title' => 'Era', 'release_date' => '2019-08-27']);
        $anniversary = GameVersion::factory()->create(['title' => 'Anniversary', 'release_date' => '2026-02-06']);
        $eraPhase = Phase::factory()->for($era)->create(['number' => '1.0', 'start_date' => '2019-08-27']);
        $later = Phase::factory()->for($anniversary)->create(['number' => '2.0', 'start_date' => '2026-05-15']);
        $earlier = Phase::factory()->for($anniversary)->create(['number' => '1.0', 'start_date' => '2026-02-06']);

        $this->assertSame([
            ['label' => 'Anniversary', 'options' => [
                ['value' => $earlier->id, 'label' => 'Phase 1'],
                ['value' => $later->id, 'label' => 'Phase 2'],
            ]],
            ['label' => 'Era', 'options' => [
                ['value' => $eraPhase->id, 'label' => 'Phase 1'],
            ]],
        ], BuildRaidFormOptions::run()['phases']);
    }

    #[Test]
    public function it_lists_phases_without_a_game_version_last(): void
    {
        $gameVersion = GameVersion::factory()->create(['title' => 'Anniversary']);
        $owned = Phase::factory()->for($gameVersion)->create(['number' => '1.0']);
        $unowned = Phase::factory()->create(['number' => '4.0', 'game_version_id' => null]);

        $this->assertSame([
            ['label' => 'Anniversary', 'options' => [['value' => $owned->id, 'label' => 'Phase 1']]],
            ['label' => 'No game version', 'options' => [['value' => $unowned->id, 'label' => 'Phase 4']]],
        ], BuildRaidFormOptions::run()['phases']);
    }

    #[Test]
    public function it_leaves_out_a_game_version_without_phases(): void
    {
        GameVersion::factory()->create();

        $this->assertSame([], BuildRaidFormOptions::run()['phases']);
    }

    #[Test]
    public function it_lists_the_banner_positions(): void
    {
        $this->assertSame(BannerPosition::options(), BuildRaidFormOptions::run()['banner_positions']);
    }
}
