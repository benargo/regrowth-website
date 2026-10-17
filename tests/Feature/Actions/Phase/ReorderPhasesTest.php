<?php

namespace Tests\Feature\Actions\Phase;

use App\Actions\Phase\ReorderPhases;
use App\Events\AddonSettingsProcessed;
use App\Models\GameVersion;
use App\Models\Phase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class ReorderPhasesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_numbers_the_phases_in_the_order_given(): void
    {
        $gameVersion = GameVersion::factory()->create();
        [$first, $second, $third] = Phase::factory()->for($gameVersion)->unscheduled()->count(3)->create();

        ReorderPhases::run([$third->id, $first->id, $second->id]);

        $this->assertSame(1, $third->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(3, $second->fresh()->sort_order);
    }

    #[Test]
    public function it_leaves_phases_outside_the_list_alone(): void
    {
        $other = Phase::factory()->for(GameVersion::factory())->unscheduled()->create();
        $phase = Phase::factory()->for(GameVersion::factory())->unscheduled()->create();

        ReorderPhases::run([$phase->id]);

        $this->assertSame(1, $other->fresh()->sort_order);
    }

    #[Test]
    public function it_fires_each_phases_updated_event(): void
    {
        $gameVersion = GameVersion::factory()->create();
        [$first, $second] = Phase::factory()->for($gameVersion)->unscheduled()->count(2)->create();
        Event::fake(AddonSettingsProcessed::class);

        ReorderPhases::run([$second->id, $first->id]);

        Event::assertDispatchedTimes(AddonSettingsProcessed::class, 2);
    }
}
