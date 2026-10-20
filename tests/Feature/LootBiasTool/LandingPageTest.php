<?php

namespace Tests\Feature\LootBiasTool;

use App\Models\GameVersion;
use App\Models\Item;
use App\Models\Phase;
use App\Models\Raid;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('loot')]
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    private GameVersion $gameVersion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gameVersion = GameVersion::factory()->create(['release_date' => Carbon::parse('2024-01-01')]);
    }

    #[Test]
    public function loot_index_allows_unauthenticated_users(): void
    {
        $response = $this->get('/loot');

        $response->assertOk();
    }

    #[Group('authorization')]
    #[Test]
    public function loot_index_allows_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
    }

    #[Group('authorization')]
    #[Test]
    public function loot_index_allows_users_with_no_roles(): void
    {
        $user = User::factory()->noRoles()->create();

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
    }

    #[Test]
    public function loot_index_renders_inertia_page(): void
    {
        $user = User::factory()->member()->create();
        $phase = Phase::factory()->started()->create();
        Raid::factory()->create(['phase_id' => $phase->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Loot/Index'));
    }

    #[Test]
    public function loot_index_passes_stats_as_props(): void
    {
        $user = User::factory()->member()->create();
        Item::factory()->count(2)->create(['game_version_id' => $this->gameVersion->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertInertia(fn ($page) => $page
            ->component('Loot/Index')
            ->where('stats.items_count', 2)
        );
    }

    #[Test]
    public function loot_index_stats_are_cached_for_ten_minutes(): void
    {
        $user = User::factory()->member()->create();

        Cache::tags(['lootcouncil'])->flush();
        Item::factory()->count(1)->create(['game_version_id' => $this->gameVersion->id]);

        $this->actingAs($user)->get('/loot');

        $this->assertTrue(Cache::tags(['lootcouncil'])->has("loot:stats:{$this->gameVersion->id}"));

        $cached = Cache::tags(['lootcouncil'])->get("loot:stats:{$this->gameVersion->id}");
        $this->assertEquals(1, $cached['items_count']);

        Item::factory()->count(1)->createQuietly(['game_version_id' => $this->gameVersion->id]);

        $response = $this->actingAs($user)->get('/loot');

        $response->assertInertia(fn ($page) => $page
            ->where('stats.items_count', 1)
        );
    }

    #[Test]
    public function loot_index_no_longer_lists_raids(): void
    {
        $response = $this->get('/loot');

        $response->assertInertia(fn ($page) => $page->component('Loot/Index')->missing('raids'));
    }
}
