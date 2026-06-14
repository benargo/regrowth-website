<?php

namespace Tests\Feature\Dashboard;

use App\Models\GuildRank;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('auth')]
class GuildRankUpdateTest extends DashboardTestCase
{
    #[Test]
    public function update_requires_authentication(): void
    {
        $rank = GuildRank::factory()->create();

        $response = $this->put(route('management.ranks.update', $rank), [
            'name' => 'New Name',
        ]);

        $response->assertRedirect('/login');
    }

    #[Group('authorization')]
    #[Test]
    public function update_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->put(route('management.ranks.update', $rank), [
            'name' => 'New Name',
        ]);

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function update_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->put(route('management.ranks.update', $rank), [
            'name' => 'New Name',
        ]);

        $response->assertForbidden();
    }

    #[Group('authorization')]
    #[Test]
    public function update_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->put(route('management.ranks.update', $rank), [
            'name' => 'New Name',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function update_allows_officer_users(): void
    {
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($this->officer)->put(route('management.ranks.update', $rank), [
            'name' => 'New Name',
        ]);

        $response->assertRedirect();
    }

    #[Group('validation')]
    #[Test]
    public function update_validates_name_required(): void
    {
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($this->officer)->put(route('management.ranks.update', $rank), []);

        $response->assertSessionHasErrors(['name']);
    }

    #[Group('validation')]
    #[Test]
    public function update_validates_name_must_be_string(): void
    {
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($this->officer)->put(route('management.ranks.update', $rank), [
            'name' => 12345,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    #[Group('validation')]
    #[Test]
    public function update_validates_name_max_length(): void
    {
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($this->officer)->put(route('management.ranks.update', $rank), [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    #[Test]
    public function update_saves_name_to_database(): void
    {
        $rank = GuildRank::factory()->create(['name' => 'Old Name']);

        $this->actingAs($this->officer)->put(route('management.ranks.update', $rank), [
            'name' => 'New Name',
        ]);

        $this->assertEquals('New Name', $rank->fresh()->name);
    }

    #[Test]
    public function update_returns_404_for_nonexistent_rank(): void
    {

        $response = $this->actingAs($this->officer)->put(route('management.ranks.update', 99999), [
            'name' => 'New Name',
        ]);

        $response->assertNotFound();
    }

    #[Test]
    public function update_clears_guild_ranks_cache(): void
    {
        $rank = GuildRank::factory()->create();

        Cache::put('guild_ranks:index', 'cached-data');
        $this->assertTrue(Cache::has('guild_ranks:index'));

        $this->actingAs($this->officer)->put(route('management.ranks.update', $rank), [
            'name' => 'New Name',
        ]);

        $this->assertFalse(Cache::has('guild_ranks:index'));
    }
}
