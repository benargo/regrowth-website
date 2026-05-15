<?php

namespace Tests\Feature\Console\Commands;

use App\Console\Commands\FetchRaidHelperEvents;
use App\Jobs\RaidHelper\FetchEvents;
use Illuminate\Support\Facades\Bus;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FetchRaidHelperEventsTest extends TestCase
{
    #[Test]
    public function it_has_the_correct_signature(): void
    {
        $command = $this->app->make(FetchRaidHelperEvents::class);

        $this->assertSame('raid-helper:fetch-events', $command->getName());
    }

    #[Test]
    public function it_has_the_correct_description(): void
    {
        $command = $this->app->make(FetchRaidHelperEvents::class);

        $this->assertSame('Fetch events from Raid Helper', $command->getDescription());
    }

    #[Test]
    public function it_dispatches_a_fetch_events_job(): void
    {
        Bus::fake();

        $this->artisan('raid-helper:fetch-events')
            ->assertSuccessful();

        Bus::assertDispatched(FetchEvents::class);
    }

    #[Test]
    public function it_displays_a_success_message_after_dispatching(): void
    {
        Bus::fake();

        $this->artisan('raid-helper:fetch-events')
            ->expectsOutputToContain('Raid Helper events fetch job dispatched successfully.')
            ->assertSuccessful();
    }
}
