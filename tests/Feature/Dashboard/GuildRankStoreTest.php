<?php

namespace Tests\Feature\Dashboard;

use App\Models\GuildRank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GuildRankStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_requires_authentication(): void
    {
        $response = $this->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_store_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();

        $response = $this->actingAs($user)->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $response->assertForbidden();
    }

    public function test_store_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();

        $response = $this->actingAs($user)->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $response->assertForbidden();
    }

    public function test_store_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();

        $response = $this->actingAs($user)->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $response->assertForbidden();
    }

    public function test_store_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $response->assertRedirect();
    }

    public function test_store_validates_name_required(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.ranks.store'), []);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_store_validates_name_must_be_string(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.ranks.store'), [
            'name' => 12345,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_store_validates_name_max_length(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->post(route('dashboard.ranks.store'), [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_store_creates_rank_in_database(): void
    {
        $user = User::factory()->officer()->create();

        $this->actingAs($user)->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $this->assertDatabaseHas('guild_ranks', [
            'name' => 'New Rank',
        ]);
    }

    public function test_store_assigns_next_available_position(): void
    {
        $user = User::factory()->officer()->create();
        GuildRank::factory()->create(['position' => 0]);
        GuildRank::factory()->create(['position' => 1]);
        GuildRank::factory()->create(['position' => 2]);

        $this->actingAs($user)->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $this->assertDatabaseHas('guild_ranks', [
            'name' => 'New Rank',
            'position' => 3,
        ]);
    }

    public function test_store_assigns_position_zero_when_no_ranks_exist(): void
    {
        $user = User::factory()->officer()->create();

        $this->actingAs($user)->post(route('dashboard.ranks.store'), [
            'name' => 'First Rank',
        ]);

        $this->assertDatabaseHas('guild_ranks', [
            'name' => 'First Rank',
            'position' => 1,
        ]);
    }

    public function test_store_clears_guild_ranks_cache(): void
    {
        $user = User::factory()->officer()->create();

        Cache::put('guild_ranks.index', 'cached-data');
        $this->assertTrue(Cache::has('guild_ranks.index'));

        $this->actingAs($user)->post(route('dashboard.ranks.store'), [
            'name' => 'New Rank',
        ]);

        $this->assertFalse(Cache::has('guild_ranks.index'));
    }
}
