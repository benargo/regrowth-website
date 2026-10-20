<?php

namespace Tests\Feature\Actions\Raid;

use App\Actions\Raid\GroupRaidsByGameVersion;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class GroupRaidsByGameVersionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_groups_raids_under_their_phases_game_version_newest_release_first(): void
    {
        $era = GameVersion::factory()->create(['title' => 'Era', 'release_date' => '2019-08-27']);
        $anniversary = GameVersion::factory()->create(['title' => 'Anniversary', 'release_date' => '2026-02-06']);
        $eraRaid = Raid::factory()->for(Phase::factory()->for($era))->create();
        $anniversaryRaid = Raid::factory()->for(Phase::factory()->for($anniversary))->create();

        $groups = GroupRaidsByGameVersion::run();

        $this->assertSame(['Anniversary', 'Era'], array_map(fn (array $group): string => $group['game_version']->title, $groups));
        $this->assertSame([$anniversaryRaid->id], $groups[0]['raids']->modelKeys());
        $this->assertSame([$eraRaid->id], $groups[1]['raids']->modelKeys());
    }

    #[Test]
    public function it_includes_a_game_version_with_no_raids(): void
    {
        GameVersion::factory()->create();

        $groups = GroupRaidsByGameVersion::run();

        $this->assertCount(1, $groups);
        $this->assertTrue($groups[0]['raids']->isEmpty());
    }

    #[Test]
    public function it_lists_raids_whose_phase_has_no_game_version_in_a_last_group(): void
    {
        GameVersion::factory()->create();
        $unowned = Raid::factory()->for(Phase::factory()->state(['game_version_id' => null]))->create();

        $groups = GroupRaidsByGameVersion::run();

        $this->assertCount(2, $groups);
        $this->assertNull($groups[1]['game_version']);
        $this->assertSame([$unowned->id], $groups[1]['raids']->modelKeys());
    }

    #[Test]
    public function it_leaves_out_the_unowned_group_when_every_raid_has_a_game_version(): void
    {
        Raid::factory()->for(Phase::factory()->for(GameVersion::factory()))->create();

        $this->assertCount(1, GroupRaidsByGameVersion::run());
    }

    #[Test]
    public function it_orders_raids_by_phase_release_then_name(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $later = Phase::factory()->for($gameVersion)->create(['start_date' => '2026-05-15 00:00:00']);
        $earlier = Phase::factory()->for($gameVersion)->create(['start_date' => '2026-01-01 00:00:00']);
        $tempestKeep = Raid::factory()->for($later)->create(['name' => 'Tempest Keep']);
        $magtheridon = Raid::factory()->for($earlier)->create(['name' => "Magtheridon's Lair"]);
        $gruul = Raid::factory()->for($earlier)->create(['name' => "Gruul's Lair"]);

        $this->assertSame(
            [$gruul->id, $magtheridon->id, $tempestKeep->id],
            GroupRaidsByGameVersion::run()[0]['raids']->modelKeys(),
        );
    }
}
