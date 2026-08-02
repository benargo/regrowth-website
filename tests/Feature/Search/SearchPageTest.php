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
    public function it_requires_a_query(): void
    {
        $this->get(route('search'))->assertSessionHasErrors(['q']);
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
}
