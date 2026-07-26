<?php

namespace Tests\Feature\Raiding;

use App\Models\Boss;
use App\Models\Phase;
use App\Models\Raid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class BossStrategiesControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_can_view_the_index(): void
    {
        Phase::factory()->create();
        Boss::factory()->for(Raid::factory())->create();

        $response = $this->get(route('raiding.boss-strategies.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/BossStrategies/Index')
            ->has('bosses')
            ->has('phases')
        );
    }

    #[Test]
    public function guests_can_view_a_boss_strategy(): void
    {
        $boss = Boss::factory()->for(Raid::factory())->create();

        $response = $this->get(route('raiding.boss-strategies.show', ['boss' => $boss, 'slug' => $boss->slug]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Raiding/BossStrategies/Show')
            ->has('boss')
        );
    }
}
