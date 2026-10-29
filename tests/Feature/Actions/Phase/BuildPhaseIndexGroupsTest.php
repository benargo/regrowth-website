<?php

namespace Tests\Feature\Actions\Phase;

use App\Actions\Phase\BuildPhaseIndexGroups;
use App\Actions\Phase\BuildPhaseRoutes;
use App\Http\Resources\PhaseResource;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class BuildPhaseIndexGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PhaseResource::setRequestScope('management');
    }

    protected function tearDown(): void
    {
        PhaseResource::clearRequestScope();

        parent::tearDown();
    }

    #[Test]
    public function it_returns_a_group_for_every_game_version_in_order(): void
    {
        $era = GameVersion::factory()->create(['title' => 'Era', 'release_date' => '2019-08-27']);
        $anniversary = GameVersion::factory()->create(['title' => 'Anniversary', 'release_date' => '2026-02-06']);
        $eraPhase = Phase::factory()->for($era)->create();
        $anniversaryPhase = Phase::factory()->for($anniversary)->create();

        $groups = $this->build();

        $this->assertCount(2, $groups);
        $this->assertSame(['Anniversary', 'Era'], array_column(array_column($groups, 'game_version'), 'title'));
        $this->assertSame([$anniversaryPhase->id], array_column($groups[0]['phases'], 'id'));
        $this->assertSame([$eraPhase->id], array_column($groups[1]['phases'], 'id'));
    }

    #[Test]
    public function it_includes_a_game_version_with_no_phases(): void
    {
        GameVersion::factory()->create();

        $groups = $this->build();

        $this->assertCount(1, $groups);
        $this->assertSame([], $groups[0]['phases']);
    }

    #[Test]
    public function it_lists_phases_without_a_game_version_in_a_last_group(): void
    {
        GameVersion::factory()->create();
        $unowned = Phase::factory()->create(['game_version_id' => null]);

        $groups = $this->build();

        $this->assertCount(2, $groups);
        $this->assertNull($groups[1]['game_version']);
        $this->assertSame([$unowned->id], array_column($groups[1]['phases'], 'id'));
    }

    #[Test]
    public function it_leaves_out_the_unowned_group_when_every_phase_has_a_game_version(): void
    {
        Phase::factory()->for(GameVersion::factory())->create();

        $this->assertCount(1, $this->build());
    }

    #[Test]
    public function it_lists_only_unowned_phases_without_a_game_version(): void
    {
        $unowned = Phase::factory()->create(['game_version_id' => null]);

        $groups = $this->build();

        $this->assertCount(1, $groups);
        $this->assertNull($groups[0]['game_version']);
        $this->assertSame([$unowned->id], array_column($groups[0]['phases'], 'id'));
    }

    #[Test]
    public function it_lists_each_groups_phases_in_release_order(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $unscheduled = Phase::factory()->for($gameVersion)->unscheduled()->create(['number' => 4]);
        $later = Phase::factory()->for($gameVersion)->create(['number' => 1, 'start_date' => '2026-05-15 00:00:00']);
        $earlier = Phase::factory()->for($gameVersion)->create(['number' => 3, 'start_date' => '2026-01-01 00:00:00']);

        $this->assertSame(
            [$earlier->id, $later->id, $unscheduled->id],
            array_column($this->build()[0]['phases'], 'id'),
        );
    }

    #[Test]
    public function it_marks_only_the_latest_started_phase_of_each_game_version_as_current(): void
    {
        $this->freezeTime();
        $gameVersion = GameVersion::factory()->create();
        Phase::factory()->for($gameVersion)->create(['start_date' => now()->subMonths(2)]);
        $current = Phase::factory()->for($gameVersion)->create(['start_date' => now()->subMonth()]);
        Phase::factory()->for($gameVersion)->upcoming()->create();

        $phases = $this->build()[0]['phases'];

        $this->assertSame([false, true, false], array_column($phases, 'is_current'));
        $this->assertSame($current->id, $phases[1]['id']);
    }

    #[Test]
    public function it_attaches_links_usage_counts_and_raids_by_name(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $phase = Phase::factory()->for($gameVersion)->create();
        Raid::factory()->for($phase)->create(['name' => 'Karazhan']);
        Raid::factory()->for($phase)->create(['name' => "Gruul's Lair"]);

        $resolved = $this->build()[0]['phases'][0];

        $this->assertSame([
            'edit' => route('management.phases.edit', $phase),
            'destroy' => route('management.phases.destroy', $phase),
        ], $resolved['links']);
        $this->assertSame(2, $resolved['raids_count']);
        $this->assertSame(0, $resolved['reports_count']);
        $this->assertSame(["Gruul's Lair", 'Karazhan'], array_column($resolved['raids'], 'name'));
    }

    // ==================== helpers ====================

    /**
     * Run the action and resolve each group's resources.
     *
     * @return list<array{game_version: array<string, mixed>|null, phases: list<array<string, mixed>>}>
     */
    private function build(): array
    {
        return array_map(fn (array $group): array => [
            'game_version' => $group['game_version']?->resolve(new Request),
            'phases' => array_values($group['phases']->resolve(new Request)),
        ], BuildPhaseIndexGroups::run(new BuildPhaseRoutes));
    }
}
