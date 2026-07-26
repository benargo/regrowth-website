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
use Tests\TestCase;

#[Group('loot')]
class SearchPageTest extends TestCase
{
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
        $item = $this->createItem('Archbishop\'s Slippers');
        $this->createItem('Thunderfury');

        $this->get(route('search', ['q' => 'slipper']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.data', 1)
                ->where('results.data.0.id', $item->id)
            );
    }

    #[Test]
    public function it_paginates_beyond_twenty_five_results(): void
    {
        foreach (range(1, 30) as $i) {
            $this->createItem("Slipper of Testing {$i}");
        }

        $this->get(route('search', ['q' => 'slipper']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.data', 25)
                ->where('results.meta.total', 30)
                ->where('results.meta.last_page', 2)
            );
    }

    #[Test]
    public function it_requires_a_query(): void
    {
        $this->get(route('search'))->assertSessionHasErrors(['q']);
    }

    #[Test]
    public function it_scopes_results_to_the_given_raid(): void
    {
        $raidA = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);
        $raidB = Raid::factory()->create(['phase_id' => $raidA->phase_id]);

        $itemA = $this->createItemForRaid('Slipper of Alpha', $raidA);
        $this->createItemForRaid('Slipper of Beta', $raidB);

        $this->get(route('search', ['q' => 'slipper', 'raid_id' => $raidA->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.data', 1)
                ->where('results.data.0.id', $itemA->id)
                ->where('scoped_raid.data.id', $raidA->id)
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
        $raid = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);

        foreach (range(1, 30) as $i) {
            $this->createItemForRaid("Slipper of Testing {$i}", $raid);
        }

        $this->get(route('search', ['q' => 'slipper', 'raid_id' => $raid->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('results.data', 25)
                ->where('results.meta.total', 30)
                ->where('results.meta.links.1.url', fn ($url) => str_contains($url, 'raid_id='.$raid->id))
            );
    }
}
