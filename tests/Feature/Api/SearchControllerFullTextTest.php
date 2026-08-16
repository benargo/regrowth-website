<?php

namespace Tests\Feature\Api;

use App\Models\Boss;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FullTextTestCase;

#[Group('loot')]
class SearchControllerFullTextTest extends FullTextTestCase
{
    /**
     * Create an item under a fresh phase/raid/boss. Pass null for a nameless item.
     */
    protected function createItem(?string $name): Item
    {
        $phase = Phase::factory()->started()->create();
        $raid = Raid::factory()->create(['phase_id' => $phase->id]);
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        $factory = Item::factory();

        if ($name !== null) {
            $factory = $factory->withName($name);
        }

        return $factory->fromBoss($boss)->create();
    }

    /**
     * Create an item under a specific raid, with its own fresh boss.
     */
    protected function createItemForRaid(?string $name, Raid $raid): Item
    {
        $boss = Boss::factory()->create(['raid_id' => $raid->id]);

        $factory = Item::factory();

        if ($name !== null) {
            $factory = $factory->withName($name);
        }

        return $factory->fromBoss($boss)->create();
    }

    #[Test]
    public function it_returns_matching_items(): void
    {
        $this->withCommittedTransaction(
            create: fn () => [
                'match' => $this->createItem('Archbishop\'s Slippers'),
                'decoy' => $this->createItem('Thunderfury'),
            ],
            assert: function (array $items) {
                $this->getJson(route('api.search', ['q' => 'slipper']))
                    ->assertOk()
                    ->assertJsonCount(1, 'data')
                    ->assertJsonPath('data.0.id', $items['match']->id)
                    ->assertJsonPath('total', 1)
                    ->assertJsonStructure([
                        'data' => [['id', 'name', 'slug', 'icon', 'wowhead' => ['url'], 'raids', 'boss']],
                        'total',
                    ]);
            },
        );
    }

    #[Test]
    public function it_returns_at_most_eight_results(): void
    {
        $this->withCommittedTransaction(
            create: fn () => array_map(fn (int $i) => $this->createItem("Slipper of Testing {$i}"), range(1, 11)),
            assert: function (array $items) {
                $this->getJson(route('api.search', ['q' => 'slipper']))
                    ->assertOk()
                    ->assertJsonCount(8, 'data')
                    ->assertJsonPath('total', 11);
            },
        );
    }

    #[Test]
    public function it_scopes_results_to_the_given_raid(): void
    {
        $this->withCommittedTransaction(
            create: function () {
                $raidA = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);
                $raidB = Raid::factory()->create(['phase_id' => $raidA->phase_id]);

                return [
                    'itemA' => $this->createItemForRaid('Slipper of Alpha', $raidA),
                    'itemB' => $this->createItemForRaid('Slipper of Beta', $raidB),
                    'raidAId' => $raidA->id,
                ];
            },
            assert: function (array $items) {
                $this->getJson(route('api.search', ['q' => 'slipper', 'raid_id' => $items['raidAId']]))
                    ->assertOk()
                    ->assertJsonCount(1, 'data')
                    ->assertJsonPath('data.0.id', $items['itemA']->id)
                    ->assertJsonPath('total', 1);
            },
        );
    }

    #[Test]
    public function it_returns_unscoped_results_when_no_raid_id_is_given(): void
    {
        $this->withCommittedTransaction(
            create: function () {
                $raidA = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);
                $raidB = Raid::factory()->create(['phase_id' => $raidA->phase_id]);

                return [
                    'itemA' => $this->createItemForRaid('Slipper of Alpha', $raidA),
                    'itemB' => $this->createItemForRaid('Slipper of Beta', $raidB),
                ];
            },
            assert: function (array $items) {
                $this->getJson(route('api.search', ['q' => 'slipper']))
                    ->assertOk()
                    ->assertJsonCount(2, 'data')
                    ->assertJsonPath('total', 2);
            },
        );
    }

    #[Test]
    public function it_returns_has_notes_instead_of_notes(): void
    {
        $this->withCommittedTransaction(
            create: function () {
                $item = $this->createItem('Archbishop\'s Slippers');
                $item->update(['notes' => 'Best in slot for warriors']);

                return ['item' => $item];
            },
            assert: function (array $items) {
                $this->getJson(route('api.search', ['q' => 'slipper']))
                    ->assertOk()
                    ->assertJsonPath('data.0.has_notes', true)
                    ->assertJsonMissingPath('data.0.notes');
            },
        );
    }

    #[Test]
    public function it_serves_a_repeated_query_from_cache(): void
    {
        $this->withCommittedTransaction(
            create: fn () => ['item' => $this->createItem('Archbishop\'s Slippers')],
            assert: function (array $items) {
                $this->getJson(route('api.search', ['q' => 'slipper']))->assertOk();

                Item::query()->delete();

                $this->getJson(route('api.search', ['q' => 'slipper']))
                    ->assertOk()
                    ->assertJsonCount(1, 'data');
            },
        );
    }

    #[Test]
    public function it_caches_scoped_and_unscoped_searches_separately(): void
    {
        $this->withCommittedTransaction(
            create: function () {
                $raid = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);

                return [
                    'inRaid' => $this->createItemForRaid('Slipper of Testing', $raid),
                    'elsewhere' => $this->createItem('Slipper of Testing Elsewhere'),
                    'raidId' => $raid->id,
                ];
            },
            assert: function (array $items) {
                // Warm the unscoped cache entry first.
                $this->getJson(route('api.search', ['q' => 'slipper']))
                    ->assertOk()
                    ->assertJsonCount(2, 'data');

                // A scoped request for the same query must not be served the unscoped cache entry.
                $this->getJson(route('api.search', ['q' => 'slipper', 'raid_id' => $items['raidId']]))
                    ->assertOk()
                    ->assertJsonCount(1, 'data')
                    ->assertJsonPath('data.0.id', $items['inRaid']->id);
            },
        );
    }

    #[Test]
    public function it_caches_different_raid_scopes_separately(): void
    {
        $this->withCommittedTransaction(
            create: function () {
                $raidA = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);
                $raidB = Raid::factory()->create(['phase_id' => $raidA->phase_id]);

                return [
                    'itemA' => $this->createItemForRaid('Slipper of Testing', $raidA),
                    'itemB' => $this->createItemForRaid('Slipper of Testing', $raidB),
                    'raidAId' => $raidA->id,
                    'raidBId' => $raidB->id,
                ];
            },
            assert: function (array $items) {
                $this->getJson(route('api.search', ['q' => 'slipper', 'raid_id' => $items['raidAId']]))
                    ->assertOk()
                    ->assertJsonCount(1, 'data')
                    ->assertJsonPath('data.0.id', $items['itemA']->id);

                $this->getJson(route('api.search', ['q' => 'slipper', 'raid_id' => $items['raidBId']]))
                    ->assertOk()
                    ->assertJsonCount(1, 'data')
                    ->assertJsonPath('data.0.id', $items['itemB']->id);
            },
        );
    }
}
