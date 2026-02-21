<?php

namespace Tests\Feature\Dashboard;

use App\Models\TBC\Phase;
use App\Models\User;
use App\Models\WarcraftLogs\GuildTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhaseViewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Holds a user model that can be used across tests. Initialized in setUp method.
     *
     * @var User
     */
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->officer()->create();
    }

    public function test_manage_phases_page_loads_with_phase_that_has_start_date(): void
    {
        Phase::factory()->started()->create();

        $response = $this->actingAs($this->user)->get(route('dashboard.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/ManagePhases')
            ->has('phases', 1)
            ->where('phases.0.start_date', fn ($value) => $value !== null)
        );
    }

    public function test_manage_phases_page_loads_with_phase_that_has_null_start_date(): void
    {
        Phase::factory()->unscheduled()->create();

        $response = $this->actingAs($this->user)->get(route('dashboard.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/ManagePhases')
            ->has('phases', 1)
            ->where('phases.0.start_date', null)
        );
    }

    public function test_manage_phases_page_loads_after_start_date_is_edited(): void
    {
        $phase = Phase::factory()->started()->create();

        $this->actingAs($this->user)->put(route('dashboard.phases.update', $phase), [
            'start_date' => '2026-03-15T14:00',
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/ManagePhases')
            ->has('phases', 1)
            ->where('phases.0.start_date', fn ($value) => str_contains($value, '2026-03-15'))
        );
    }

    public function test_manage_phases_page_loads_after_start_date_is_set_to_null(): void
    {
        $phase = Phase::factory()->started()->create();

        $this->actingAs($this->user)->put(route('dashboard.phases.update', $phase), [
            'start_date' => null,
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/ManagePhases')
            ->has('phases', 1)
            ->where('phases.0.start_date', null)
        );
    }

    public function test_manage_phases_page_loads_after_tags_are_added_to_phase(): void
    {
        $phase = Phase::factory()->create();
        $tag1 = GuildTag::factory()->create();
        $tag2 = GuildTag::factory()->create();

        $this->actingAs($this->user)->put(route('dashboard.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => [$tag1->id, $tag2->id],
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/ManagePhases')
            ->has('phases', 1)
            ->has('phases.0.guild_tags', 2)
        );
    }

    public function test_manage_phases_page_loads_after_tags_are_removed_from_phase(): void
    {
        $phase = Phase::factory()->create();
        GuildTag::factory()->withPhase($phase)->create();
        GuildTag::factory()->withPhase($phase)->create();

        $this->actingAs($this->user)->put(route('dashboard.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => [],
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/ManagePhases')
            ->has('phases', 1)
            ->has('phases.0.guild_tags', 0)
        );
    }

    public function test_manage_phases_page_loads_after_tag_attendance_is_enabled(): void
    {
        $phase = Phase::factory()->create();
        $tag = GuildTag::factory()->doesNotCountAttendance()->withPhase($phase)->create();

        $this->actingAs($this->user)->patch(route('wcl.guild-tags.toggle-attendance', $tag), [
            'count_attendance' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/ManagePhases')
            ->has('phases', 1)
            ->has('phases.0.guild_tags', 1)
            ->where('phases.0.guild_tags.0.count_attendance', true)
        );
    }

    public function test_manage_phases_page_loads_after_tag_attendance_is_disabled(): void
    {
        $phase = Phase::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withPhase($phase)->create();

        $this->actingAs($this->user)->patch(route('wcl.guild-tags.toggle-attendance', $tag), [
            'count_attendance' => false,
        ]);

        $response = $this->actingAs($this->user)->get(route('dashboard.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard/ManagePhases')
            ->has('phases', 1)
            ->has('phases.0.guild_tags', 1)
            ->where('phases.0.guild_tags.0.count_attendance', false)
        );
    }
}
