<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuildTagControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_count_attendance_requires_authentication(): void
    {
        $tag = GuildTag::factory()->create();

        $response = $this->patch(route('wcl.guild-tags.toggle-attendance', $tag), [
            'count_attendance' => true,
        ]);

        $response->assertRedirect('/login');
    }

    public function test_toggle_count_attendance_forbids_guest_users(): void
    {
        $user = User::factory()->guest()->create();
        $tag = GuildTag::factory()->create();

        $response = $this->actingAs($user)->patch(route('wcl.guild-tags.toggle-attendance', $tag), [
            'count_attendance' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_toggle_count_attendance_forbids_member_users(): void
    {
        $user = User::factory()->member()->create();
        $tag = GuildTag::factory()->create();

        $response = $this->actingAs($user)->patch(route('wcl.guild-tags.toggle-attendance', $tag), [
            'count_attendance' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_toggle_count_attendance_forbids_raider_users(): void
    {
        $user = User::factory()->raider()->create();
        $tag = GuildTag::factory()->create();

        $response = $this->actingAs($user)->patch(route('wcl.guild-tags.toggle-attendance', $tag), [
            'count_attendance' => true,
        ]);

        $response->assertForbidden();
    }

    public function test_toggle_count_attendance_allows_officer_users(): void
    {
        $user = User::factory()->officer()->create();
        $tag = GuildTag::factory()->doesNotCountAttendance()->create();

        $response = $this->actingAs($user)->patch(route('wcl.guild-tags.toggle-attendance', $tag), [
            'count_attendance' => true,
        ]);

        $response->assertRedirect();
    }

    public function test_toggle_count_attendance_can_enable_attendance(): void
    {
        $user = User::factory()->officer()->create();
        $tag = GuildTag::factory()->doesNotCountAttendance()->create();

        $this->assertFalse($tag->count_attendance);

        $this->actingAs($user)->patch(route('wcl.guild-tags.toggle-attendance', $tag), [
            'count_attendance' => true,
        ]);

        $tag->refresh();

        $this->assertTrue($tag->count_attendance);
    }

    public function test_toggle_count_attendance_can_disable_attendance(): void
    {
        $user = User::factory()->officer()->create();
        $tag = GuildTag::factory()->countsAttendance()->create();

        $this->assertTrue($tag->count_attendance);

        $this->actingAs($user)->patch(route('wcl.guild-tags.toggle-attendance', $tag), [
            'count_attendance' => false,
        ]);

        $tag->refresh();

        $this->assertFalse($tag->count_attendance);
    }

    public function test_toggle_count_attendance_validates_count_attendance_is_required(): void
    {
        $user = User::factory()->officer()->create();
        $tag = GuildTag::factory()->create();

        $response = $this->actingAs($user)->patch(route('wcl.guild-tags.toggle-attendance', $tag), []);

        $response->assertSessionHasErrors(['count_attendance']);
    }

    public function test_toggle_count_attendance_validates_count_attendance_must_be_boolean(): void
    {
        $user = User::factory()->officer()->create();
        $tag = GuildTag::factory()->create();

        $response = $this->actingAs($user)->patch(route('wcl.guild-tags.toggle-attendance', $tag), [
            'count_attendance' => 'not-a-boolean',
        ]);

        $response->assertSessionHasErrors(['count_attendance']);
    }

    public function test_toggle_count_attendance_does_not_affect_other_tags(): void
    {
        $user = User::factory()->officer()->create();
        $tag1 = GuildTag::factory()->doesNotCountAttendance()->create();
        $tag2 = GuildTag::factory()->countsAttendance()->create();

        $this->actingAs($user)->patch(route('wcl.guild-tags.toggle-attendance', $tag1), [
            'count_attendance' => true,
        ]);

        $tag1->refresh();
        $tag2->refresh();

        $this->assertTrue($tag1->count_attendance);
        $this->assertTrue($tag2->count_attendance);
    }
}
