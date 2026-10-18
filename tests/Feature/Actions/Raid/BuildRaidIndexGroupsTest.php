<?php

namespace Tests\Feature\Actions\Raid;

use App\Actions\Raid\BuildRaidIndexGroups;
use App\Actions\Raid\BuildRaidRoutes;
use App\Http\Resources\RaidResource;
use App\Models\Boss;
use App\Models\Event;
use App\Models\GameVersion;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class BuildRaidIndexGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RaidResource::setRequestScope('management');
    }

    protected function tearDown(): void
    {
        RaidResource::clearRequestScope();

        parent::tearDown();
    }

    #[Test]
    public function it_resolves_one_group_per_game_version_then_the_unowned_raids(): void
    {
        $era = GameVersion::factory()->create(['title' => 'Era', 'release_date' => '2019-08-27']);
        GameVersion::factory()->create(['title' => 'Anniversary', 'release_date' => '2026-02-06']);
        $eraRaid = Raid::factory()->for(Phase::factory()->for($era))->create();
        $unowned = Raid::factory()->for(Phase::factory()->state(['game_version_id' => null]))->create();

        $groups = $this->build();

        $this->assertSame(['Anniversary', 'Era'], [$groups[0]['game_version']['title'], $groups[1]['game_version']['title']]);
        $this->assertSame([], $groups[0]['raids']);
        $this->assertSame([$eraRaid->id], array_column($groups[1]['raids'], 'id'));
        $this->assertNull($groups[2]['game_version']);
        $this->assertSame([$unowned->id], array_column($groups[2]['raids'], 'id'));
    }

    #[Test]
    public function it_attaches_links_the_phase_and_every_count(): void
    {
        $this->freezeTime();
        $gameVersion = GameVersion::factory()->create();
        $raid = Raid::factory()->for(Phase::factory()->for($gameVersion)->state(['number' => '1.0']))->withItems(2)->create();
        Boss::factory()->for($raid)->create();
        $upcoming = Event::factory()->create(['start_time' => now()->addDay(), 'end_time' => now()->addDay()->addHours(3)]);
        $past = Event::factory()->create(['start_time' => now()->subDays(2), 'end_time' => now()->subDays(2)->addHours(3)]);
        $template = Event::factory()->template()->create(['start_time' => null, 'end_time' => null]);
        $raid->events()->attach([
            $upcoming->id => ['sort_order' => 1],
            $past->id => ['sort_order' => 1],
            $template->id => ['sort_order' => 1],
        ]);

        $resolved = $this->build()[0]['raids'][0];

        $this->assertSame([
            'edit' => route('management.raids.edit', $raid),
            'destroy' => route('management.raids.destroy', $raid),
        ], $resolved['links']);
        $this->assertSame('Phase 1', $resolved['phase']['name']);
        $this->assertSame(1, $resolved['bosses_count']);
        $this->assertSame(1, $resolved['upcoming_events_count']);
        $this->assertSame(1, $resolved['past_events_count']);
        $this->assertSame(1, $resolved['template_events_count']);
        $this->assertSame(2, $resolved['items_count']);
    }

    #[Test]
    public function it_counts_every_groups_raids_in_one_query(): void
    {
        $first = GameVersion::factory()->create();
        $second = GameVersion::factory()->create();
        Raid::factory()->for(Phase::factory()->for($first))->count(2)->create();
        Raid::factory()->for(Phase::factory()->for($second))->count(2)->create();

        DB::enableQueryLog();
        BuildRaidIndexGroups::run(new BuildRaidRoutes);
        $countQueries = collect(DB::getQueryLog())->filter(
            fn (array $query): bool => str_contains($query['query'], 'as `bosses_count`'),
        );

        $this->assertCount(1, $countQueries);
    }

    // ==================== helpers ====================

    /**
     * Run the action and resolve each group's resources.
     *
     * @return list<array{game_version: array<string, mixed>|null, raids: list<array<string, mixed>>}>
     */
    private function build(): array
    {
        return array_map(fn (array $group): array => [
            'game_version' => $group['game_version']?->resolve(new Request),
            'raids' => array_values($group['raids']->resolve(new Request)),
        ], BuildRaidIndexGroups::run(new BuildRaidRoutes));
    }
}
