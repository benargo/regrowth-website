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
    public function it_never_returns_items_with_a_null_name(): void
    {
        $this->createItem(null);

        $this->getJson(route('api.search', ['q' => 'slipper']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
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
