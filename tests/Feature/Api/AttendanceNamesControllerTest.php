<?php

namespace Tests\Feature\Api;

use App\Models\Character;
use App\Models\DiscordRole;
use App\Models\GuildRank;
use App\Models\GuildTag;
use App\Models\Permission;
use App\Models\Raids\Report;
use App\Models\User;
use App\Services\Blizzard\BlizzardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceNamesControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $officer;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Cache::tags(['attendance', 'reports'])->flush();

        $permission = Permission::firstOrCreate(['name' => 'view-attendance', 'guard_name' => 'web']);
        $officerRole = DiscordRole::firstOrCreate(
            ['id' => '829021769448816691'],
            ['name' => 'Officer', 'position' => 5, 'is_visible' => true]
        );
        $officerRole->givePermissionTo($permission);

        $this->officer = User::factory()->officer()->create();

        $this->mock(BlizzardService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('findPlayableClass')->andReturn([]);
            $mock->shouldReceive('getPlayableClassMedia')->andReturn(['assets' => []]);
            $mock->shouldReceive('getGuildRoster')->andReturn(['members' => []]);
        });
    }

    // ==================== Access Control ====================

    #[Test]
    public function requires_authentication(): void
    {
        $response = $this->getJson(route('api.attendance.names'));

        $response->assertUnauthorized();
    }

    #[Test]
    public function forbids_users_without_view_attendance_permission(): void
    {
        $member = User::factory()->member()->create();

        $response = $this->actingAs($member)->getJson(route('api.attendance.names'));

        $response->assertForbidden();
    }

    #[Test]
    public function allows_users_with_view_attendance_permission(): void
    {
        $response = $this->actingAs($this->officer)->getJson(route('api.attendance.names'));

        $response->assertOk();
    }

    // ==================== Core Behaviour ====================

    #[Test]
    public function returns_null_when_character_id_is_absent(): void
    {
        $response = $this->actingAs($this->officer)->getJson(route('api.attendance.names'));

        $response->assertOk()->assertExactJson([]);
    }

    #[Test]
    public function returns_null_when_character_is_not_in_any_report(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['rank_id' => $rank->id]);

        $response = $this->actingAs($this->officer)->getJson(
            route('api.attendance.names', ['character_id' => $character->id])
        );

        $response->assertOk()->assertExactJson([]);
    }

    #[Test]
    public function returns_attendance_names_for_a_simple_character(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 20:00', 'UTC')]);
        $report->characters()->attach($character->id, ['presence' => 1]);

        $response = $this->actingAs($this->officer)->getJson(
            route('api.attendance.names', ['character_id' => $character->id])
        );

        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertContains('Thrall', $data[0]);
    }

    #[Test]
    public function returns_merged_attendance_names_for_main_and_alt(): void
    {
        $rank = GuildRank::factory()->create();
        $main = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $alt = Character::factory()->create(['name' => 'ThrallAlt', 'is_main' => false, 'rank_id' => $rank->id]);

        \DB::table('character_links')->insert([
            ['character_id' => $main->id, 'linked_character_id' => $alt->id, 'created_at' => now(), 'updated_at' => now()],
            ['character_id' => $alt->id, 'linked_character_id' => $main->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 20:00', 'UTC')]);
        $report->characters()->attach($main->id, ['presence' => 1]);
        $report->characters()->attach($alt->id, ['presence' => 1]);

        $response = $this->actingAs($this->officer)->getJson(
            route('api.attendance.names', ['character_id' => $main->id, 'combine_linked_characters' => 1])
        );

        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertContains('Thrall', $data[0]);
        $this->assertContains('ThrallAlt', $data[0]);
    }

    #[Test]
    public function returns_empty_array_for_raid_where_no_presence(): void
    {
        $rank = GuildRank::factory()->create();
        $character = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 20:00', 'UTC')]);
        $report->characters()->attach($character->id, ['presence' => 0]);

        $response = $this->actingAs($this->officer)->getJson(
            route('api.attendance.names', ['character_id' => $character->id])
        );

        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame([], $data[0]);
    }

    #[Test]
    public function respects_combine_linked_characters_false(): void
    {
        $rank = GuildRank::factory()->create();
        $main = Character::factory()->main()->create(['name' => 'Thrall', 'rank_id' => $rank->id]);
        $alt = Character::factory()->create(['name' => 'ThrallAlt', 'is_main' => false, 'rank_id' => $rank->id]);

        \DB::table('character_links')->insert([
            ['character_id' => $main->id, 'linked_character_id' => $alt->id, 'created_at' => now(), 'updated_at' => now()],
            ['character_id' => $alt->id, 'linked_character_id' => $main->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $tag = GuildTag::factory()->countsAttendance()->withoutPhase()->create();
        $report = Report::factory()->withGuildTag($tag)->create(['start_time' => Carbon::parse('2025-01-15 20:00', 'UTC')]);
        $report->characters()->attach($main->id, ['presence' => 1]);
        $report->characters()->attach($alt->id, ['presence' => 1]);

        $response = $this->actingAs($this->officer)->getJson(
            route('api.attendance.names', ['character_id' => $main->id, 'combine_linked_characters' => 0])
        );

        // Without merging, attendanceNames is never populated on raw rows — the response is null/empty.
        // This confirms alt names are not included when character merging is disabled.
        $response->assertOk()->assertExactJson([]);
    }

    #[Test]
    public function returns_json_not_inertia_envelope(): void
    {
        $response = $this->actingAs($this->officer)->getJson(route('api.attendance.names'));

        $response->assertOk();
        $this->assertArrayNotHasKey('component', $response->json() ?? []);
        $this->assertArrayNotHasKey('props', $response->json() ?? []);
    }
}
