<?php

namespace Tests\Feature\Api;

use App\Models\Boss;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\InteractsWithFullTextSearch;
use Tests\TestCase;

#[Group('loot')]
class SearchControllerTest extends TestCase
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
        $this->usingModel(Phase::class, Raid::class, Boss::class, Item::class)->withCommittedTransaction(
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
                        'data' => [['id', 'name', 'slug', 'icon', 'wowhead' => ['url'], 'raid', 'boss']],
                        'total',
                    ]);
            },
        );
    }

    #[Test]
    public function it_returns_at_most_eight_results(): void
    {
        $this->usingModel(Phase::class, Raid::class, Boss::class, Item::class)->withCommittedTransaction(
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
                $this->getJson(route('api.search', ['q' => 'slipper', 'raid_id' => $raidAId]))
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
        $this->usingModel(Phase::class, Raid::class, Boss::class, Item::class)->withCommittedTransaction(
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
    public function it_never_returns_items_with_a_null_name(): void
    {
        $this->createItem(null);

        $this->getJson(route('api.search', ['q' => 'slipper']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
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
                $this->getJson(route('api.search', ['q' => 'slipper']))
                    ->assertOk()
                    ->assertJsonPath('data.0.has_notes', true)
                    ->assertJsonMissingPath('data.0.notes');
            },
        );
    }

    // ==========================================
    // Caching
    // ==========================================

    #[Test]
    public function it_serves_a_repeated_query_from_cache(): void
    {
        $this->usingModel(Phase::class, Raid::class, Boss::class, Item::class)->withCommittedTransaction(
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
        $raidId = null;

        $this->usingModel(Phase::class, Raid::class, Boss::class, Item::class)->withCommittedTransaction(
            create: function () use (&$raidId) {
                $raid = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);
                $raidId = $raid->id;

                return [
                    'inRaid' => $this->createItemForRaid('Slipper of Testing', $raid),
                    'elsewhere' => $this->createItem('Slipper of Testing Elsewhere'),
                ];
            },
            assert: function (array $items) use (&$raidId) {
                // Warm the unscoped cache entry first.
                $this->getJson(route('api.search', ['q' => 'slipper']))
                    ->assertOk()
                    ->assertJsonCount(2, 'data');

                // A scoped request for the same query must not be served the unscoped cache entry.
                $this->getJson(route('api.search', ['q' => 'slipper', 'raid_id' => $raidId]))
                    ->assertOk()
                    ->assertJsonCount(1, 'data')
                    ->assertJsonPath('data.0.id', $items['inRaid']->id);
            },
        );
    }

    #[Test]
    public function it_caches_different_raid_scopes_separately(): void
    {
        $raidAId = null;
        $raidBId = null;

        $this->usingModel(Phase::class, Raid::class, Boss::class, Item::class)->withCommittedTransaction(
            create: function () use (&$raidAId, &$raidBId) {
                $raidA = Raid::factory()->create(['phase_id' => Phase::factory()->started()->create()->id]);
                $raidB = Raid::factory()->create(['phase_id' => $raidA->phase_id]);
                $raidAId = $raidA->id;
                $raidBId = $raidB->id;

                return [
                    'itemA' => $this->createItemForRaid('Slipper of Testing', $raidA),
                    'itemB' => $this->createItemForRaid('Slipper of Testing', $raidB),
                ];
            },
            assert: function (array $items) use (&$raidAId, &$raidBId) {
                $this->getJson(route('api.search', ['q' => 'slipper', 'raid_id' => $raidAId]))
                    ->assertOk()
                    ->assertJsonCount(1, 'data')
                    ->assertJsonPath('data.0.id', $items['itemA']->id);

                $this->getJson(route('api.search', ['q' => 'slipper', 'raid_id' => $raidBId]))
                    ->assertOk()
                    ->assertJsonCount(1, 'data')
                    ->assertJsonPath('data.0.id', $items['itemB']->id);
            },
        );
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
