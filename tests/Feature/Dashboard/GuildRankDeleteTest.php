<?php

namespace Tests\Feature\Dashboard;

use App\Models\GuildRank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GuildRankDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_requires_authentication(): void
    {
        $rank = GuildRank::factory()->create();

        $response = $this->delete(route('dashboard.ranks.destroy', $rank));

        $response->assertRedirect('/login');
    }

    public function test_delete_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.ranks.destroy', $rank));

        $response->assertForbidden();
    }

    public function test_delete_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.ranks.destroy', $rank));

        $response->assertForbidden();
    }

    public function test_delete_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.ranks.destroy', $rank));

        $response->assertForbidden();
    }

    public function test_delete_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.ranks.destroy', $rank));

        $response->assertRedirect();
    }

    public function test_delete_removes_rank_from_database(): void
    {
        $user = User::factory()->officer()->create();
        $rank = GuildRank::factory()->create();
        $rankId = $rank->id;

        $this->actingAs($user)->delete(route('dashboard.ranks.destroy', $rank));

        $this->assertDatabaseMissing('guild_ranks', ['id' => $rankId]);
    }

    public function test_delete_returns_404_for_nonexistent_rank(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.ranks.destroy', 99999));

        $response->assertNotFound();
    }

    public function test_delete_clears_guild_ranks_cache(): void
    {
        $user = User::factory()->officer()->create();
        $rank = GuildRank::factory()->create();

        Cache::put('guild_ranks.index', 'cached-data');
        $this->assertTrue(Cache::has('guild_ranks.index'));

        $this->actingAs($user)->delete(route('dashboard.ranks.destroy', $rank));

        $this->assertFalse(Cache::has('guild_ranks.index'));
    }
}
