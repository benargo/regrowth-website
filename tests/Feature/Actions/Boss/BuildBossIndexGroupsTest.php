<?php

namespace Tests\Feature\Actions\Boss;

use App\Actions\Boss\BuildBossIndexGroups;
use App\Http\Resources\BossResource;
use App\Jobs\BuildAddonExportFile;
use App\Models\Boss;
use App\Models\Event;
use App\Models\EventAssignment;
use App\Models\GameVersion;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class BuildBossIndexGroupsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        BossResource::setRequestScope('management');
    }

    protected function tearDown(): void
    {
        BossResource::clearRequestScope();

        parent::tearDown();
    }

    #[Test]
    public function it_returns_a_group_for_every_game_version_then_raids_without_one(): void
    {
        $era = GameVersion::factory()->create(['title' => 'Era', 'release_date' => '2019-08-27']);
        GameVersion::factory()->create(['title' => 'Anniversary', 'release_date' => '2026-02-06']);
        $this->raidIn($era, 'Molten Core');
        Raid::factory()->for(Phase::factory()->state(['game_version_id' => null]))->create(['name' => 'Naxxramas']);

        $groups = $this->build();

        $this->assertSame(['Anniversary', 'Era'], [$groups[0]['game_version']['title'], $groups[1]['game_version']['title']]);
        $this->assertSame([], $groups[0]['raids']);
        $this->assertSame(['Molten Core'], array_column(array_column($groups[1]['raids'], 'raid'), 'name'));
        $this->assertNull($groups[2]['game_version']);
        $this->assertSame(['Naxxramas'], array_column(array_column($groups[2]['raids'], 'raid'), 'name'));
    }

    #[Test]
    public function it_lists_each_raids_bosses_in_encounter_order_and_keeps_a_raid_without_bosses(): void
    {
        $phase = Phase::factory()->for(GameVersion::factory())->create();
        $karazhan = Raid::factory()->for($phase)->create(['name' => 'Karazhan']);
        Raid::factory()->for($phase)->create(['name' => "Gruul's Lair"]);
        $moroes = Boss::factory()->for($karazhan)->create(['name' => 'Moroes']);
        $attumen = Boss::factory()->for($karazhan)->create(['name' => 'Attumen the Huntsman']);
        $attumen->update(['sort_order' => 1]);
        $moroes->update(['sort_order' => 2]);

        $raids = $this->build()[0]['raids'];

        $this->assertSame(["Gruul's Lair", 'Karazhan'], array_column(array_column($raids, 'raid'), 'name'));
        $this->assertSame([], $raids[0]['bosses']);
        $this->assertSame(['Attumen the Huntsman', 'Moroes'], array_column($raids[1]['bosses'], 'name'));
    }

    #[Test]
    public function it_attaches_links_usage_counts_past_link_counts_and_the_strategy_flag(): void
    {
        $this->freezeTime();
        Bus::fake([BuildAddonExportFile::class]);
        $boss = Boss::factory()->for($this->raidIn(GameVersion::factory()->create(), 'Karazhan'))->create(['notes' => null]);
        Item::factory()->fromBoss($boss)->create();
        $upcoming = Event::factory()->live()->create(['start_time' => now()->addDay(), 'end_time' => now()->addDay()->addHours(3)]);
        $past = Event::factory()->live()->create(['start_time' => now()->subDays(2), 'end_time' => now()->subDays(2)->addHours(3)]);
        $template = Event::factory()->template()->create(['start_time' => null, 'end_time' => null]);

        foreach ([$upcoming, $past, $template] as $event) {
            $event->bosses()->attach($boss->id, ['sort_order' => 1]);
        }

        EventAssignment::factory()->forBoss($boss)->create(['event_id' => $past->id]);

        $resolved = $this->build()[0]['raids'][0]['bosses'][0];

        $this->assertSame([
            'edit' => route('management.bosses.edit', $boss),
            'destroy' => route('management.bosses.destroy', $boss),
        ], $resolved['links']);
        $this->assertSame(1, $resolved['items_count']);
        $this->assertSame(1, $resolved['upcoming_events_count']);
        $this->assertSame(0, $resolved['upcoming_assignments_count']);
        $this->assertSame(0, $resolved['upcoming_assignment_groups_count']);
        $this->assertSame(1, $resolved['past_events_count']);
        $this->assertSame(1, $resolved['past_assignments_count']);
        $this->assertSame(1, $resolved['template_events_count']);
        $this->assertSame(['has_strategy' => false], $resolved['meta']);
    }

    // ==================== helpers ====================

    private function raidIn(GameVersion $gameVersion, string $name): Raid
    {
        return Raid::factory()->for(Phase::factory()->for($gameVersion))->create(['name' => $name]);
    }

    /**
     * Run the action and resolve each group's resources.
     *
     * @return list<array{game_version: array<string, mixed>|null, raids: list<array{raid: array<string, mixed>, bosses: list<array<string, mixed>>}>}>
     */
    private function build(): array
    {
        return array_map(fn (array $group): array => [
            'game_version' => $group['game_version']?->resolve(new Request),
            'raids' => array_map(fn (array $raid): array => [
                'raid' => $raid['raid']->resolve(new Request),
                'bosses' => array_values($raid['bosses']->resolve(new Request)),
            ], $group['raids']),
        ], BuildBossIndexGroups::run());
    }
}
