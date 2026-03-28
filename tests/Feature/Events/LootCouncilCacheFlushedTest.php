<?php

namespace Tests\Feature\Events;

use App\Events\LootCouncilCacheFlushed;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LootCouncilCacheFlushedTest extends TestCase
{
    #[Test]
    public function it_can_be_dispatched(): void
    {
        Event::fake();

        LootCouncilCacheFlushed::dispatch();

        Event::assertDispatched(LootCouncilCacheFlushed::class);
    }
}
