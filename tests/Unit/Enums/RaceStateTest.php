<?php

namespace Tests\Unit\Enums;

use App\Enums\RaceState;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class RaceStateTest extends TestCase
{
    #[Test]
    public function it_is_running_without_a_bronze_placement(): void
    {
        $this->assertSame(RaceState::Running, RaceState::fromBronzeReachedAt(null));
    }

    #[Test]
    public function it_is_closing_inside_the_honourable_mention_window(): void
    {
        $this->travelTo(now());

        $this->assertSame(RaceState::Closing, RaceState::fromBronzeReachedAt(now()->subHours(47)->subMinutes(59)));
    }

    #[Test]
    public function it_is_finished_once_the_window_has_elapsed(): void
    {
        $this->travelTo(now());

        $this->assertSame(RaceState::Finished, RaceState::fromBronzeReachedAt(now()->subHours(48)));
    }
}
