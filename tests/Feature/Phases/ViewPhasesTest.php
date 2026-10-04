<?php

namespace Tests\Feature\Phases;

use App\Models\Phase;
use App\Models\WarcraftLogs\GuildTag;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Saloon\Laravel\Facades\Saloon;
use Tests\Support\DashboardTestCase;

#[Group('phases')]
#[Group('raiding')]
class ViewPhasesTest extends DashboardTestCase
{
    #[Test]
    public function manage_phases_page_loads_with_phase_that_has_start_date(): void
    {
        Phase::factory()->started()->create();

        $response = $this->actingAs($this->officer)->get(route('management.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Manage/Phases/Index')
            ->has('phases', 1)
            ->where('phases.0.start_date', fn ($value) => $value !== null)
        );
    }

    #[Test]
    public function manage_phases_page_loads_with_phase_that_has_null_start_date(): void
    {
        Phase::factory()->unscheduled()->create();

        $response = $this->actingAs($this->officer)->get(route('management.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Manage/Phases/Index')
            ->has('phases', 1)
            ->where('phases.0.start_date', null)
        );
    }

    #[Test]
    public function manage_phases_page_loads_after_start_date_is_edited(): void
    {
        $phase = Phase::factory()->started()->create();

        $this->actingAs($this->officer)->put(route('management.phases.update', $phase), [
            'start_date' => '2026-03-15T14:00',
        ]);

        $response = $this->actingAs($this->officer)->get(route('management.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Manage/Phases/Index')
            ->has('phases', 1)
            ->where('phases.0.start_date', fn ($value) => str_contains($value, '2026-03-15'))
        );
    }

    #[Test]
    public function manage_phases_page_loads_after_start_date_is_set_to_null(): void
    {
        $phase = Phase::factory()->started()->create();

        $this->actingAs($this->officer)->put(route('management.phases.update', $phase), [
            'start_date' => null,
        ]);

        $response = $this->actingAs($this->officer)->get(route('management.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Manage/Phases/Index')
            ->has('phases', 1)
            ->where('phases.0.start_date', null)
        );
    }

    #[Test]
    public function manage_phases_page_loads_after_tags_are_added_to_phase(): void
    {
        $phase = Phase::factory()->create();
        $tag1 = GuildTag::factory()->create();
        $tag2 = GuildTag::factory()->create();

        $this->actingAs($this->officer)->put(route('management.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => [$tag1->id, $tag2->id],
        ]);

        $response = $this->actingAs($this->officer)->get(route('management.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Manage/Phases/Index')
            ->has('phases', 1)
            ->has('phases.0.guild_tags', 2)
        );
    }

    #[Test]
    public function manage_phases_page_loads_after_tags_are_removed_from_phase(): void
    {
        $phase = Phase::factory()->create();
        GuildTag::factory()->withPhase($phase)->create();
        GuildTag::factory()->withPhase($phase)->create();

        $this->actingAs($this->officer)->put(route('management.phases.guild-tags.update', $phase), [
            'guild_tag_ids' => [],
        ]);

        $response = $this->actingAs($this->officer)->get(route('management.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Manage/Phases/Index')
            ->has('phases', 1)
            ->has('phases.0.guild_tags', 0)
        );
    }

    #[Test]
    public function manage_phases_page_loads_after_tag_attendance_is_enabled(): void
    {
        $phase = Phase::factory()->create();
        $tag = GuildTag::factory()->doesNotCountAttendance()->withPhase($phase)->create();

        $this->actingAs($this->officer)->patch(route('wcl.guild-tags.toggle-attendance', $tag), [
            'count_attendance' => true,
        ]);

        $response = $this->actingAs($this->officer)->get(route('management.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Manage/Phases/Index')
            ->has('phases', 1)
            ->has('phases.0.guild_tags', 1)
            ->where('phases.0.guild_tags.0.count_attendance', true)
        );
    }

    #[Test]
    public function manage_phases_page_loads_after_tag_attendance_is_disabled(): void
    {
        $phase = Phase::factory()->create();
        $tag = GuildTag::factory()->countsAttendance()->withPhase($phase)->create();

        $this->actingAs($this->officer)->patch(route('wcl.guild-tags.toggle-attendance', $tag), [
            'count_attendance' => false,
        ]);

        $response = $this->actingAs($this->officer)->get(route('management.phases.view'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Manage/Phases/Index')
            ->has('phases', 1)
            ->has('phases.0.guild_tags', 1)
            ->where('phases.0.guild_tags.0.count_attendance', false)
        );
    }

    #[Test]
    #[Group('warcraftlogs-integration')]
    public function all_guild_tags_lists_every_stored_tag_by_name_without_calling_warcraft_logs(): void
    {
        Saloon::fake([]);

        $zulu = GuildTag::factory()->withoutPhase()->create(['name' => 'Zulu']);
        $firstAlpha = GuildTag::factory()->withoutPhase()->create(['name' => 'Alpha']);
        $secondAlpha = GuildTag::factory()->withoutPhase()->create(['name' => 'Alpha']);

        $response = $this->actingAs($this->officer)->get(route('management.phases.view'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Phases/Index')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('all_guild_tags.data', 3)
                ->where('all_guild_tags.data.0.id', $firstAlpha->id)
                ->where('all_guild_tags.data.1.id', $secondAlpha->id)
                ->where('all_guild_tags.data.2.id', $zulu->id)
                ->where('all_guild_tags.data.2.name', 'Zulu')
            )
        );

        Saloon::assertNothingSent();
    }
}
