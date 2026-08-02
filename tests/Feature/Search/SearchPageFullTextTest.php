<?php

namespace Tests\Feature\Search;

use App\Models\Boss;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\FullTextTestCase;

#[Group('loot')]
class SearchPageFullTextTest extends FullTextTestCase
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

        return $factory->create([
            'raid_id' => $raid->id,
            'boss_id' => $boss->id,
        ]);
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

        return $factory->create([
            'raid_id' => $raid->id,
            'boss_id' => $boss->id,
        ]);
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
                $this->get(route('search', ['q' => 'slipper']))
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page
                        ->has('results.data', 1)
                        ->where('results.data.0.id', $items['match']->id)
                    );
            },
        );
    }

    #[Test]
    public function it_paginates_beyond_twenty_five_results(): void
    {
        $this->withCommittedTransaction(
            create: fn () => array_map(fn (int $i) => $this->createItem("Slipper of Testing {$i}"), range(1, 30)),
            assert: function (array $items) {
                $this->get(route('search', ['q' => 'slipper']))
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page
                        ->has('results.data', 25)
                        ->where('results.meta.total', 30)
                        ->where('results.meta.last_page', 2)
                    );
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
                $this->get(route('search', ['q' => 'slipper']))
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page
                        ->where('results.data.0.has_notes', true)
                        ->missing('results.data.0.notes')
                    );
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
                $this->get(route('search', ['q' => 'slipper', 'raid_id' => $items['raidAId']]))
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page
                        ->has('results.data', 1)
                        ->where('results.data.0.id', $items['itemA']->id)
                        ->where('scoped_raid.data.id', $items['raidAId'])
                    );
            },
        );
    }

    #[Test]
    public function it_preserves_raid_id_across_pagination_links(): void
    {
        $this->withCommittedTransaction(
            create: function () {
                $raid = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);

                return [
                    'items' => array_map(fn (int $i) => $this->createItemForRaid("Slipper of Testing {$i}", $raid), range(1, 30)),
                    'raidId' => $raid->id,
                ];
            },
            assert: function (array $items) {
                $this->get(route('search', ['q' => 'slipper', 'raid_id' => $items['raidId']]))
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page
                        ->has('results.data', 25)
                        ->where('results.meta.total', 30)
                        ->where('results.meta.links.1.url', fn ($url) => str_contains($url, 'raid_id='.$items['raidId']))
                    );
            },
        );
    }
}
