<?php

namespace Tests\Feature\Search;

use App\Models\Boss;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithFullTextSearch;
use Tests\TestCase;

#[Group('loot')]
class SearchPageTest extends TestCase
{
    use InteractsWithFullTextSearch;
    use RefreshDatabase;

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
    public function it_renders_the_search_page_for_unauthenticated_users(): void
    {
        $this->createItem('Archbishop\'s Slippers');

        $this->get(route('search', ['q' => 'slipper']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Search')
                ->has('results.data')
                ->has('results.meta')
                ->where('q', 'slipper')
            );
    }

    #[Test]
    public function it_returns_matching_items(): void
    {
        $this->usingModel(Phase::class, Raid::class, Boss::class, Item::class)->withCommittedTransaction(
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
        $this->usingModel(Phase::class, Raid::class, Boss::class, Item::class)->withCommittedTransaction(
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
    public function it_requires_a_query(): void
    {
        $this->get(route('search'))->assertSessionHasErrors(['q']);
    }

    #[Test]
    public function it_returns_has_notes_instead_of_notes(): void
    {
        $this->usingModel(Phase::class, Raid::class, Boss::class, Item::class)->withCommittedTransaction(
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
        $raidAId = null;

        $this->usingModel(Phase::class, Raid::class, Boss::class, Item::class)->withCommittedTransaction(
            create: function () use (&$raidAId) {
                $raidA = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);
                $raidB = Raid::factory()->create(['phase_id' => $raidA->phase_id]);
                $raidAId = $raidA->id;

                return [
                    'itemA' => $this->createItemForRaid('Slipper of Alpha', $raidA),
                    'itemB' => $this->createItemForRaid('Slipper of Beta', $raidB),
                ];
            },
            assert: function (array $items) use (&$raidAId) {
                $this->get(route('search', ['q' => 'slipper', 'raid_id' => $raidAId]))
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page
                        ->has('results.data', 1)
                        ->where('results.data.0.id', $items['itemA']->id)
                        ->where('scoped_raid.data.id', $raidAId)
                    );
            },
        );
    }

    #[Test]
    public function it_has_a_null_scoped_raid_when_unscoped(): void
    {
        $this->createItem('Archbishop\'s Slippers');

        $this->get(route('search', ['q' => 'slipper']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scoped_raid', null)
            );
    }

    #[Test]
    public function it_preserves_raid_id_across_pagination_links(): void
    {
        $raidId = null;

        $this->usingModel(Phase::class, Raid::class, Boss::class, Item::class)->withCommittedTransaction(
            create: function () use (&$raidId) {
                $raid = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);
                $raidId = $raid->id;

                return array_map(fn (int $i) => $this->createItemForRaid("Slipper of Testing {$i}", $raid), range(1, 30));
            },
            assert: function (array $items) use (&$raidId) {
                $this->get(route('search', ['q' => 'slipper', 'raid_id' => $raidId]))
                    ->assertOk()
                    ->assertInertia(fn (Assert $page) => $page
                        ->has('results.data', 25)
                        ->where('results.meta.total', 30)
                        ->where('results.meta.links.1.url', fn ($url) => str_contains($url, 'raid_id='.$raidId))
                    );
            },
        );
    }
}
