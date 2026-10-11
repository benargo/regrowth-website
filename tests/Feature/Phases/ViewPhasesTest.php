<?php

namespace Tests\Feature\Phases;

use App\Models\Phase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
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
    public function manage_phases_page_no_longer_sends_guild_tags(): void
    {
        Phase::factory()->create();

        $response = $this->actingAs($this->officer)->get(route('management.phases.view'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Manage/Phases/Index')
            ->missing('phases.0.guild_tags')
            ->missing('all_guild_tags')
        );
    }
}
