<?php

namespace Tests\Feature\Dashboard;

use App\Models\GuildRank;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

class GuildRankStoreTest extends DashboardTestCase
{
    #[Test]
    public function store_requires_authentication(): void
    {
        $response = $this->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $response->assertRedirect('/login');
    }

    #[Test]
    public function store_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function store_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function store_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function store_allows_officer_users(): void
    {

        $response = $this->actingAs($this->officer)->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $response->assertRedirect();
    }

    #[Test]
    public function store_validates_name_required(): void
    {

        $response = $this->actingAs($this->officer)->post(route('dashboard.ranks.store'), []);

        $response->assertSessionHasErrors(['name']);
    }

    #[Test]
    public function store_validates_name_must_be_string(): void
    {

        $response = $this->actingAs($this->officer)->post(route('dashboard.ranks.store'), [
            'name' => 12345,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    #[Test]
    public function store_validates_name_max_length(): void
    {

        $response = $this->actingAs($this->officer)->post(route('dashboard.ranks.store'), [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    #[Test]
    public function store_creates_rank_in_database(): void
    {

        $this->actingAs($this->officer)->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $this->assertDatabaseHas('guild_ranks', [
            'name' => 'New Rank',
        ]);
    }

    #[Test]
    public function store_assigns_next_available_position(): void
    {
        GuildRank::factory()->create(['position' => 0]);
        GuildRank::factory()->create(['position' => 1]);
        GuildRank::factory()->create(['position' => 2]);

        $this->actingAs($this->officer)->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $this->assertDatabaseHas('guild_ranks', [
            'name' => 'New Rank',
            'position' => 3,
        ]);
    }

    #[Test]
    public function store_assigns_position_zero_when_no_ranks_exist(): void
    {

        $this->actingAs($this->officer)->post(route('dashboard.ranks.store'), [
            'name' => 'First Rank',
        ]);

        $this->assertDatabaseHas('guild_ranks', [
            'name' => 'First Rank',
            'position' => 1,
        ]);
    }

    #[Test]
    public function store_clears_guild_ranks_cache(): void
    {

        Cache::put('guild_ranks:index', 'cached-data');
        $this->assertTrue(Cache::has('guild_ranks:index'));

        $this->actingAs($this->officer)->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $this->assertFalse(Cache::has('guild_ranks:index'));
    }
}
