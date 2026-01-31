<?php

namespace Tests\Feature\Dashboard;

use App\Models\GuildRank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuildRankUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_requires_authentication(): void
    {
        $rank = GuildRank::factory()->create();

        $response = $this->put(route('dashboard.ranks.update', $rank), [
            'name' => 'New Name',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_update_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.ranks.update', $rank), [
            'name' => 'New Name',
        ]);

        $response->assertForbidden();
    }

    public function test_update_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.ranks.update', $rank), [
            'name' => 'New Name',
        ]);

        $response->assertForbidden();
    }

    public function test_update_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.ranks.update', $rank), [
            'name' => 'New Name',
        ]);

        $response->assertForbidden();
    }

    public function test_update_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.ranks.update', $rank), [
            'name' => 'New Name',
        ]);

        $response->assertRedirect();
    }

    public function test_update_validates_name_required(): void
    {
        $user = User::factory()->officer()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.ranks.update', $rank), []);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_update_validates_name_must_be_string(): void
    {
        $user = User::factory()->officer()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.ranks.update', $rank), [
            'name' => 12345,
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_update_validates_name_max_length(): void
    {
        $user = User::factory()->officer()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->put(route('dashboard.ranks.update', $rank), [
            'name' => str_repeat('a', 256),
        ]);

        $response->assertSessionHasErrors(['name']);
    }

    public function test_update_saves_name_to_database(): void
    {
        $user = User::factory()->officer()->create();
        $rank = GuildRank::factory()->create(['name' => 'Old Name']);

        $this->actingAs($user)->put(route('dashboard.ranks.update', $rank), [
            'name' => 'New Name',
        ]);

        $this->assertEquals('New Name', $rank->fresh()->name);
    }

    public function test_update_returns_404_for_nonexistent_rank(): void
    {
        $user = User::factory()->officer()->create();

        $response = $this->actingAs($user)->put(route('dashboard.ranks.update', 99999), [
            'name' => 'New Name',
        ]);

        $response->assertNotFound();
    }
}
