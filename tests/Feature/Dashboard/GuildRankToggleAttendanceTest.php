<?php

namespace Tests\Feature\Dashboard;

use App\Models\GuildRank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GuildRankToggleAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_count_attendance_requires_authentication(): void
    {
        $rank = GuildRank::factory()->create();

        $response = $this->patch(route('dashboard.ranks.toggle-attendance', $rank), [
            'count_attendance' => true,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_toggle_count_attendance_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->patch(route('dashboard.ranks.toggle-attendance', $rank), [
            'count_attendance' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_toggle_count_attendance_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->patch(route('dashboard.ranks.toggle-attendance', $rank), [
            'count_attendance' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_toggle_count_attendance_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->patch(route('dashboard.ranks.toggle-attendance', $rank), [
            'count_attendance' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_toggle_count_attendance_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $rank = GuildRank::factory()->doesNotCountAttendance()->create();

        $response = $this->actingAs($user)->patch(route('dashboard.ranks.toggle-attendance', $rank), [
            'count_attendance' => true,
        ]);

        $response->assertRedirect();
    }

    public function test_toggle_count_attendance_can_enable_attendance(): void
    {
        $user = User::factory()->officer()->create();
        $rank = GuildRank::factory()->doesNotCountAttendance()->create();

        $this->assertFalse($rank->count_attendance);

        $this->actingAs($user)->patch(route('dashboard.ranks.toggle-attendance', $rank), [
            'count_attendance' => true,
        ]);

        $rank->refresh();

        $this->assertTrue($rank->count_attendance);
    }

    public function test_toggle_count_attendance_can_disable_attendance(): void
    {
        $user = User::factory()->officer()->create();
        $rank = GuildRank::factory()->create(['count_attendance' => true]);

        $this->assertTrue($rank->count_attendance);

        $this->actingAs($user)->patch(route('dashboard.ranks.toggle-attendance', $rank), [
            'count_attendance' => false,
        ]);

        $rank->refresh();

        $this->assertFalse($rank->count_attendance);
    }

    public function test_toggle_count_attendance_validates_count_attendance_is_required(): void
    {
        $user = User::factory()->officer()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->patch(route('dashboard.ranks.toggle-attendance', $rank), []);

        $response->assertSessionHasErrors(['count_attendance']);
    }

    public function test_toggle_count_attendance_validates_count_attendance_must_be_boolean(): void
    {
        $user = User::factory()->officer()->create();
        $rank = GuildRank::factory()->create();

        $response = $this->actingAs($user)->patch(route('dashboard.ranks.toggle-attendance', $rank), [
            'count_attendance' => 'not-a-boolean',
        ]);

        $response->assertSessionHasErrors(['count_attendance']);
    }

    public function test_toggle_count_attendance_does_not_affect_other_ranks(): void
    {
        $user = User::factory()->officer()->create();
        $rank1 = GuildRank::factory()->doesNotCountAttendance()->create();
        $rank2 = GuildRank::factory()->create(['count_attendance' => true]);

        $this->actingAs($user)->patch(route('dashboard.ranks.toggle-attendance', $rank1), [
            'count_attendance' => true,
        ]);

        $rank1->refresh();
        $rank2->refresh();

        $this->assertTrue($rank1->count_attendance);
        $this->assertTrue($rank2->count_attendance);
    }

    public function test_toggle_count_attendance_clears_guild_ranks_cache(): void
    {
        $user = User::factory()->officer()->create();
        $rank = GuildRank::factory()->doesNotCountAttendance()->create();

        Cache::put('guild_ranks.index', 'cached-data');
        $this->assertTrue(Cache::has('guild_ranks.index'));

        $this->actingAs($user)->patch(route('dashboard.ranks.toggle-attendance', $rank), [
            'count_attendance' => true,
        ]);

        $this->assertFalse(Cache::has('guild_ranks.index'));
    }
}
