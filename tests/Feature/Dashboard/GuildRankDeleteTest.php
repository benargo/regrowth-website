<?php

namespace Tests\Feature\Dashboard;

use App\Models\GuildRank;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

class GuildRankDeleteTest extends DashboardTestCase
{
    #[Test]
    public function delete_requires_authentication(): void
    {
        $rank = GuildRank::factory()->create();

        $response = $this->delete(route('dashboard.ranks.destroy', $rank));

        $response->assertRedirect('/login');
    }

    #[Test]
    public function delete_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.ranks.destroy', $rank));

        $response->assertForbidden();
    }

    #[Test]
    public function delete_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.ranks.destroy', $rank));

        $response->assertForbidden();
    }

    #[Test]
    public function delete_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->delete(route('dashboard.ranks.destroy', $rank));

        $response->assertForbidden();
    }

    #[Test]
    public function delete_allows_officer_users(): void
    {
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($this->officer)->delete(route('dashboard.ranks.destroy', $rank));

        $response->assertRedirect();
    }

    #[Test]
    public function delete_removes_rank_from_database(): void
    {
        $rank = GuildRank::factory()->create();
        $rankId = $rank->id;

        $this->actingAs($this->officer)->delete(route('dashboard.ranks.destroy', $rank));

        $this->assertDatabaseMissing('guild_ranks', ['id' => $rankId]);
    }

    #[Test]
    public function delete_returns_404_for_nonexistent_rank(): void
    {

        $response = $this->actingAs($this->officer)->delete(route('dashboard.ranks.destroy', 99999));

        $response->assertNotFound();
    }

    #[Test]
    public function delete_clears_guild_ranks_cache(): void
    {
        $rank = GuildRank::factory()->create();

        Cache::put('guild_ranks:index', 'cached-data');
        $this->assertTrue(Cache::has('guild_ranks:index'));

        $this->actingAs($this->officer)->delete(route('dashboard.ranks.destroy', $rank));

        $this->assertFalse(Cache::has('guild_ranks:index'));
    }
}
