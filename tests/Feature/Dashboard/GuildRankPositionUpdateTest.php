<?php

namespace Tests\Feature\Dashboard;

use App\Models\GuildRank;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

class GuildRankPositionUpdateTest extends DashboardTestCase
{
    #[Test]
    public function update_positions_requires_authentication(): void
    {
        $ranks = GuildRank::factory()->count(3)->create();

        $response = $this->post(route('dashboard.ranks.update-positions'), [
            'ranks' => $ranks->map(fn ($rank) => ['id' => $rank->id, 'position' => $rank->position])->toArray(),
        ]);

        $response->assertRedirect('/login');
    }

    #[Test]
    public function update_positions_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $ranks = GuildRank::factory()->count(3)->create();

        $response = $this->actingAs($user)->post(route('dashboard.ranks.update-positions'), [
            'ranks' => $ranks->map(fn ($rank) => ['id' => $rank->id, 'position' => $rank->position])->toArray(),
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function update_positions_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $ranks = GuildRank::factory()->count(3)->create();

        $response = $this->actingAs($user)->post(route('dashboard.ranks.update-positions'), [
            'ranks' => $ranks->map(fn ($rank) => ['id' => $rank->id, 'position' => $rank->position])->toArray(),
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function update_positions_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $ranks = GuildRank::factory()->count(3)->create();

        $response = $this->actingAs($user)->post(route('dashboard.ranks.update-positions'), [
            'ranks' => $ranks->map(fn ($rank) => ['id' => $rank->id, 'position' => $rank->position])->toArray(),
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function update_positions_allows_officer_users(): void
    {
        $ranks = GuildRank::factory()->count(3)->create();

        $response = $this->actingAs($this->officer)->post(route('dashboard.ranks.update-positions'), [
            'ranks' => $ranks->map(fn ($rank) => ['id' => $rank->id, 'position' => $rank->position])->toArray(),
        ]);

        $response->assertRedirect();
    }

    #[Test]
    public function update_positions_validates_ranks_required(): void
    {

        $response = $this->actingAs($this->officer)->post(route('dashboard.ranks.update-positions'), []);

        $response->assertSessionHasErrors(['ranks']);
    }

    #[Test]
    public function update_positions_validates_ranks_must_be_array(): void
    {

        $response = $this->actingAs($this->officer)->post(route('dashboard.ranks.update-positions'), [
            'ranks' => 'not-an-array',
        ]);

        $response->assertSessionHasErrors(['ranks']);
    }

    #[Test]
    public function update_positions_validates_rank_id_required(): void
    {

        $response = $this->actingAs($this->officer)->post(route('dashboard.ranks.update-positions'), [
            'ranks' => [
                ['position' => 0],
            ],
        ]);

        $response->assertSessionHasErrors(['ranks.0.id']);
    }

    #[Test]
    public function update_positions_validates_rank_id_exists(): void
    {

        $response = $this->actingAs($this->officer)->post(route('dashboard.ranks.update-positions'), [
            'ranks' => [
                ['id' => 99999, 'position' => 0],
            ],
        ]);

        $response->assertSessionHasErrors(['ranks.0.id']);
    }

    #[Test]
    public function update_positions_validates_position_required(): void
    {
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('dashboard.ranks.update-positions'), [
            'ranks' => [
                ['id' => $rank->id],
            ],
        ]);

        $response->assertSessionHasErrors(['ranks.0.position']);
    }

    #[Test]
    public function update_positions_validates_position_must_be_integer(): void
    {
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('dashboard.ranks.update-positions'), [
            'ranks' => [
                ['id' => $rank->id, 'position' => 'not-an-integer'],
            ],
        ]);

        $response->assertSessionHasErrors(['ranks.0.position']);
    }

    #[Test]
    public function update_positions_validates_position_must_be_non_negative(): void
    {
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($this->officer)->post(route('dashboard.ranks.update-positions'), [
            'ranks' => [
                ['id' => $rank->id, 'position' => -1],
            ],
        ]);

        $response->assertSessionHasErrors(['ranks.0.position']);
    }

    #[Test]
    public function update_positions_saves_positions_to_database(): void
    {
        $rank1 = GuildRank::factory()->create(['position' => 0]);
        $rank2 = GuildRank::factory()->create(['position' => 1]);
        $rank3 = GuildRank::factory()->create(['position' => 2]);

        $this->actingAs($this->officer)->post(route('dashboard.ranks.update-positions'), [
            'ranks' => [
                ['id' => $rank1->id, 'position' => 2],
                ['id' => $rank2->id, 'position' => 0],
                ['id' => $rank3->id, 'position' => 1],
            ],
        ]);

        $this->assertEquals(2, $rank1->fresh()->position);
        $this->assertEquals(0, $rank2->fresh()->position);
        $this->assertEquals(1, $rank3->fresh()->position);
    }

    #[Test]
    public function update_positions_can_swap_two_ranks(): void
    {
        $rank1 = GuildRank::factory()->create(['position' => 0, 'name' => 'Guild Master']);
        $rank2 = GuildRank::factory()->create(['position' => 1, 'name' => 'Officer']);

        $this->actingAs($this->officer)->post(route('dashboard.ranks.update-positions'), [
            'ranks' => [
                ['id' => $rank1->id, 'position' => 1],
                ['id' => $rank2->id, 'position' => 0],
            ],
        ]);

        $this->assertEquals(1, $rank1->fresh()->position);
        $this->assertEquals(0, $rank2->fresh()->position);
    }

    #[Test]
    public function update_positions_can_update_single_rank(): void
    {
        $rank = GuildRank::factory()->create(['position' => 5]);

        $this->actingAs($this->officer)->post(route('dashboard.ranks.update-positions'), [
            'ranks' => [
                ['id' => $rank->id, 'position' => 0],
            ],
        ]);

        $this->assertEquals(0, $rank->fresh()->position);
    }

    #[Test]
    public function update_positions_clears_guild_ranks_cache(): void
    {
        $rank = GuildRank::factory()->create();

        Cache::put('guild_ranks.index', 'cached-data');
        $this->assertTrue(Cache::has('guild_ranks.index'));

        $this->actingAs($this->officer)->post(route('dashboard.ranks.update-positions'), [
            'ranks' => [
                ['id' => $rank->id, 'position' => $rank->position],
            ],
        ]);

        $this->assertFalse(Cache::has('guild_ranks.index'));
    }
}
