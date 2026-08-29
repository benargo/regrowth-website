<?php

namespace Tests\Feature\Actions;

use App\Actions\EventBossResolver;
use App\Http\Integrations\RaidHelper\Data\Zones\ZoneData;
use App\Models\Boss;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
#[Group('raidhelper-integration')]
class EventBossResolverTest extends TestCase
{
    use RefreshDatabase;

    // ==================== fromZones ====================

    #[Test]
    #[Group('happy-path')]
    public function it_takes_every_boss_of_a_zone_whose_bosses_key_is_absent(): void
    {
        $raid = Raid::factory()->create();
        $first = Boss::factory()->for($raid)->order(1)->create();
        $second = Boss::factory()->for($raid)->order(2)->create();

        $zones = collect([ZoneData::from(['id' => $raid->id, 'name' => $raid->name])]);

        $bosses = $this->resolver()->fromZones($zones, collect([$raid->id => $raid]));

        $this->assertSame([$first->id, $second->id], $bosses->pluck('id')->all());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_orders_absent_key_bosses_by_sort_order(): void
    {
        $raid = Raid::factory()->create();
        $late = Boss::factory()->for($raid)->order(2)->create();
        $early = Boss::factory()->for($raid)->order(1)->create();

        $zones = collect([ZoneData::from(['id' => $raid->id, 'name' => $raid->name])]);

        $bosses = $this->resolver()->fromZones($zones, collect([$raid->id => $raid]));

        $this->assertSame([$early->id, $late->id], $bosses->pluck('id')->all());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_takes_only_the_bosses_named_in_an_explicit_list(): void
    {
        $raid = Raid::factory()->create();
        $wanted = Boss::factory()->for($raid)->order(1)->create();
        $skipped = Boss::factory()->for($raid)->order(2)->create();

        $zones = collect([ZoneData::from([
            'id' => $raid->id,
            'name' => $raid->name,
            'bosses' => [['id' => $wanted->id, 'name' => $wanted->name]],
        ])]);

        $bosses = $this->resolver()->fromZones($zones, collect([$raid->id => $raid]));

        $this->assertSame([$wanted->id], $bosses->pluck('id')->all());
        $this->assertNotContains($skipped->id, $bosses->pluck('id')->all());
    }

    #[Test]
    #[Group('edge-case')]
    public function it_returns_no_bosses_for_a_zone_with_an_empty_explicit_list(): void
    {
        $raid = Raid::factory()->create();
        Boss::factory()->for($raid)->create();

        $zones = collect([ZoneData::from([
            'id' => $raid->id,
            'name' => $raid->name,
            'bosses' => [],
        ])]);

        $bosses = $this->resolver()->fromZones($zones, collect([$raid->id => $raid]));

        $this->assertTrue($bosses->isEmpty());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_honours_an_explicit_boss_order_hint_over_sort_order(): void
    {
        $raid = Raid::factory()->create();
        $a = Boss::factory()->for($raid)->order(1)->create();
        $b = Boss::factory()->for($raid)->order(2)->create();

        $zones = collect([ZoneData::from([
            'id' => $raid->id,
            'name' => $raid->name,
            'bosses' => [
                ['id' => $a->id, 'name' => $a->name, 'order' => 2],
                ['id' => $b->id, 'name' => $b->name, 'order' => 1],
            ],
        ])]);

        $bosses = $this->resolver()->fromZones($zones, collect([$raid->id => $raid]));

        $this->assertSame([$b->id, $a->id], $bosses->pluck('id')->all());
    }

    #[Test]
    #[Group('happy-path')]
    public function it_emits_zones_in_payload_order(): void
    {
        $first = Raid::factory()->create();
        $second = Raid::factory()->create();
        $firstBoss = Boss::factory()->for($first)->order(1)->create();
        $secondBoss = Boss::factory()->for($second)->order(1)->create();

        $zones = collect([
            ZoneData::from(['id' => $second->id, 'name' => $second->name]),
            ZoneData::from(['id' => $first->id, 'name' => $first->name]),
        ]);

        $raids = collect([$first->id => $first, $second->id => $second]);

        $bosses = $this->resolver()->fromZones($zones, $raids);

        $this->assertSame([$secondBoss->id, $firstBoss->id], $bosses->pluck('id')->all());
    }

    #[Test]
    #[Group('edge-case')]
    public function it_ignores_a_zone_whose_raid_was_not_resolved(): void
    {
        $known = Raid::factory()->create();
        $knownBoss = Boss::factory()->for($known)->order(1)->create();
        $unknown = Raid::factory()->create();
        Boss::factory()->for($unknown)->create();

        $zones = collect([
            ZoneData::from(['id' => $unknown->id, 'name' => $unknown->name]),
            ZoneData::from(['id' => $known->id, 'name' => $known->name]),
        ]);

        $bosses = $this->resolver()->fromZones($zones, collect([$known->id => $known]));

        $this->assertSame([$knownBoss->id], $bosses->pluck('id')->all());
    }

    #[Test]
    #[Group('error-handling')]
    public function it_skips_and_logs_a_boss_id_that_belongs_to_another_raid(): void
    {
        Log::spy();

        $raid = Raid::factory()->create();
        $ourBoss = Boss::factory()->for($raid)->order(1)->create();
        $foreignBoss = Boss::factory()->for(Raid::factory())->create();

        $zones = collect([ZoneData::from([
            'id' => $raid->id,
            'name' => $raid->name,
            'bosses' => [
                ['id' => $ourBoss->id, 'name' => $ourBoss->name],
                ['id' => $foreignBoss->id, 'name' => $foreignBoss->name],
            ],
        ])]);

        $bosses = $this->resolver()->fromZones($zones, collect([$raid->id => $raid]));

        $this->assertSame([$ourBoss->id], $bosses->pluck('id')->all());

        Log::shouldHaveReceived('error')->once()->withArgs(function (string $message, array $context) use ($raid, $foreignBoss): bool {
            return str_contains($message, 'EventBossResolver')
                && $context['zone_id'] === $raid->id
                && $context['boss_id'] === $foreignBoss->id;
        });
    }

    #[Test]
    #[Group('edge-case')]
    public function it_returns_an_empty_collection_when_given_no_zones(): void
    {
        $bosses = $this->resolver()->fromZones(collect(), collect());

        $this->assertTrue($bosses->isEmpty());
    }

    // ==================== fromRaidIds ====================

    #[Test]
    #[Group('happy-path')]
    public function it_resolves_every_boss_of_the_given_raids_in_raid_order(): void
    {
        $first = Raid::factory()->create();
        $second = Raid::factory()->create();
        $firstBossA = Boss::factory()->for($first)->order(1)->create();
        $firstBossB = Boss::factory()->for($first)->order(2)->create();
        $secondBoss = Boss::factory()->for($second)->order(1)->create();

        $bosses = $this->resolver()->fromRaidIds(collect([$second->id, $first->id]));

        $this->assertSame(
            [$secondBoss->id, $firstBossA->id, $firstBossB->id],
            $bosses->pluck('id')->all(),
        );
    }

    #[Test]
    #[Group('happy-path')]
    public function it_orders_bosses_within_a_raid_by_sort_order(): void
    {
        $raid = Raid::factory()->create();
        $late = Boss::factory()->for($raid)->order(2)->create();
        $early = Boss::factory()->for($raid)->order(1)->create();

        $bosses = $this->resolver()->fromRaidIds(collect([$raid->id]));

        $this->assertSame([$early->id, $late->id], $bosses->pluck('id')->all());
    }

    #[Test]
    #[Group('edge-case')]
    public function it_yields_nothing_for_a_raid_id_with_no_bosses(): void
    {
        $raid = Raid::factory()->create();

        $bosses = $this->resolver()->fromRaidIds(collect([$raid->id]));

        $this->assertTrue($bosses->isEmpty());
    }

    #[Test]
    #[Group('edge-case')]
    public function it_returns_an_empty_collection_when_given_no_raid_ids(): void
    {
        Boss::factory()->for(Raid::factory())->create();

        $bosses = $this->resolver()->fromRaidIds(collect());

        $this->assertTrue($bosses->isEmpty());
    }

    // ==================== helpers ====================

    private function resolver(): EventBossResolver
    {
        return new EventBossResolver;
    }
}
