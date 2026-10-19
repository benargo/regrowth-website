<?php

namespace Tests\Feature\Dashboard;

use App\Models\Boss;
use App\Models\Phase;
use App\Models\Raid;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DashboardTestCase;

#[Group('raiding')]
class BossStrategyControllerTest extends DashboardTestCase
{
    #[Test]
    public function guests_can_view_the_raiding_index(): void
    {
        Phase::factory()->create();
        Boss::factory()->for(Raid::factory())->create();

        $response = $this->get(route('raiding.boss-strategies.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Raiding/BossStrategies/Index')
            ->has('bosses')
            ->has('phases')
        );
    }

    #[Test]
    public function officers_see_the_raiding_index_via_the_raiding_route(): void
    {
        Phase::factory()->create();
        Boss::factory()->for(Raid::factory())->create();

        $response = $this->actingAs($this->officer)->get(route('raiding.boss-strategies.index'));

        $response->assertInertia(fn ($page) => $page->component('Raiding/BossStrategies/Index'));
    }

    #[Test]
    public function index_orders_bosses_by_raid_then_sort_order(): void
    {
        $lowerRaid = Raid::factory()->create();
        $higherRaid = Raid::factory()->create();
        $higherRaidSecondBoss = Boss::factory()->for($higherRaid)->order(2)->create();
        $lowerRaidSecondBoss = Boss::factory()->for($lowerRaid)->order(2)->create();
        $higherRaidFirstBoss = Boss::factory()->for($higherRaid)->order(1)->create();
        $lowerRaidFirstBoss = Boss::factory()->for($lowerRaid)->order(1)->create();

        $response = $this->get(route('raiding.boss-strategies.index'));

        $response->assertInertia(fn ($page) => $page->where(
            'bosses.data',
            fn ($groups) => collect($groups)->flatten(1)->pluck('id')->all() === [
                $lowerRaidFirstBoss->id,
                $lowerRaidSecondBoss->id,
                $higherRaidFirstBoss->id,
                $higherRaidSecondBoss->id,
            ],
        ));
    }

    #[Test]
    public function index_lists_phases_in_release_order(): void
    {
        $unscheduled = Phase::factory()->unscheduled()->create(['number' => 1]);
        $earlier = Phase::factory()->create(['number' => 3, 'start_date' => '2026-01-01 00:00:00']);

        $response = $this->get(route('raiding.boss-strategies.index'));

        $response->assertInertia(fn ($page) => $page
            ->where('phases.data.0.id', $earlier->id)
            ->where('phases.data.1.id', $unscheduled->id)
        );
    }

    // ==================== show ====================

    #[Test]
    public function guests_can_view_a_boss_strategy(): void
    {
        $boss = Boss::factory()->for(Raid::factory())->create();

        $response = $this->get(route('raiding.boss-strategies.show', ['boss' => $boss, 'slug' => $boss->slug]));

        $response->assertInertia(fn ($page) => $page
            ->component('Raiding/BossStrategies/Show')
            ->has('boss')
        );
    }
}
