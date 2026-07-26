<?php

namespace Tests\Feature\Api;

use App\Models\Boss;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class SearchControllerTest extends TestCase
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

    // ==========================================
    // Validation
    // ==========================================

    #[Test]
    public function it_rejects_a_non_existent_raid_id(): void
    {
        $this->getJson(route('api.search', ['q' => 'slipper', 'raid_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['raid_id']);
    }

    #[Test]
    public function it_rejects_a_non_integer_raid_id(): void
    {
        $this->getJson(route('api.search', ['q' => 'slipper', 'raid_id' => 'not-a-number']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['raid_id']);
    }

    #[Test]
    public function it_requires_a_query(): void
    {
        $this->getJson(route('api.search'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function it_rejects_a_single_character_query(): void
    {
        $this->getJson(route('api.search', ['q' => 'a']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    #[Test]
    public function it_rejects_a_query_over_one_hundred_characters(): void
    {
        $this->getJson(route('api.search', ['q' => str_repeat('a', 101)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    // ==========================================
    // Results
    // ==========================================

    #[Test]
    public function it_is_reachable_without_authentication(): void
    {
        $this->createItem('Archbishop\'s Slippers');

        $this->getJson(route('api.search', ['q' => 'slipper']))->assertOk();
    }

    #[Test]
    public function it_returns_matching_items(): void
    {
        $item = $this->createItem('Archbishop\'s Slippers');
        $this->createItem('Thunderfury');

        $this->getJson(route('api.search', ['q' => 'slipper']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('total', 1)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'slug', 'icon', 'wowhead' => ['url'], 'raid', 'boss']],
                'total',
            ]);
    }

    #[Test]
    public function it_returns_at_most_eight_results(): void
    {
        foreach (range(1, 11) as $i) {
            $this->createItem("Slipper of Testing {$i}");
        }

        $this->getJson(route('api.search', ['q' => 'slipper']))
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonPath('total', 11);
    }

    #[Test]
    public function it_scopes_results_to_the_given_raid(): void
    {
        $raidA = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);
        $raidB = Raid::factory()->create(['phase_id' => $raidA->phase_id]);

        $itemA = $this->createItemForRaid('Slipper of Alpha', $raidA);
        $this->createItemForRaid('Slipper of Beta', $raidB);

        $this->getJson(route('api.search', ['q' => 'slipper', 'raid_id' => $raidA->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $itemA->id)
            ->assertJsonPath('total', 1);
    }

    #[Test]
    public function it_returns_unscoped_results_when_no_raid_id_is_given(): void
    {
        $raidA = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);
        $raidB = Raid::factory()->create(['phase_id' => $raidA->phase_id]);

        $this->createItemForRaid('Slipper of Alpha', $raidA);
        $this->createItemForRaid('Slipper of Beta', $raidB);

        $this->getJson(route('api.search', ['q' => 'slipper']))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 2);
    }

    #[Test]
    public function it_never_returns_items_with_a_null_name(): void
    {
        $this->createItem(null);

        $this->getJson(route('api.search', ['q' => 'slipper']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // ==========================================
    // Caching
    // ==========================================

    #[Test]
    public function it_serves_a_repeated_query_from_cache(): void
    {
        $this->createItem('Archbishop\'s Slippers');

        $this->getJson(route('api.search', ['q' => 'slipper']))->assertOk();

        Item::query()->delete();

        $this->getJson(route('api.search', ['q' => 'slipper']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_caches_scoped_and_unscoped_searches_separately(): void
    {
        $raid = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);
        $inRaid = $this->createItemForRaid('Slipper of Testing', $raid);
        $this->createItem('Slipper of Testing Elsewhere');

        // Warm the unscoped cache entry first.
        $this->getJson(route('api.search', ['q' => 'slipper']))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // A scoped request for the same query must not be served the unscoped cache entry.
        $this->getJson(route('api.search', ['q' => 'slipper', 'raid_id' => $raid->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $inRaid->id);
    }

    #[Test]
    public function it_caches_different_raid_scopes_separately(): void
    {
        $raidA = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);
        $raidB = Raid::factory()->create(['phase_id' => $raidA->phase_id]);

        $itemA = $this->createItemForRaid('Slipper of Testing', $raidA);
        $itemB = $this->createItemForRaid('Slipper of Testing', $raidB);

        $this->getJson(route('api.search', ['q' => 'slipper', 'raid_id' => $raidA->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $itemA->id);

        $this->getJson(route('api.search', ['q' => 'slipper', 'raid_id' => $raidB->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $itemB->id);
    }

    // ==========================================
    // Rate limiting
    // ==========================================

    #[Test]
    public function it_rate_limits_past_sixty_requests_a_minute(): void
    {
        $this->createItem('Archbishop\'s Slippers');

        foreach (range(1, 60) as $i) {
            $this->getJson(route('api.search', ['q' => "slipper {$i}"]))->assertOk();
        }

        $this->getJson(route('api.search', ['q' => 'slipper 61']))
            ->assertStatus(429);
    }
}
