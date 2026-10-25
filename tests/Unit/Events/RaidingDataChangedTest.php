<?php

namespace Tests\Unit\Events;

use App\Contracts\Events\FlushesRaidingCache;
use App\Events\RaidingDataChanged;
use App\Models\Boss;
use App\Models\Raid;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class RaidingDataChangedTest extends TestCase
{
    #[Group('contract')]
    #[Test]
    public function it_flushes_the_raiding_cache(): void
    {
        $this->assertInstanceOf(FlushesRaidingCache::class, new RaidingDataChanged(new Boss));
    }

    #[Group('contract')]
    #[Test]
    public function it_is_dispatched_after_commit(): void
    {
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, new RaidingDataChanged(new Boss));
    }

    #[Test]
    public function it_exposes_the_model_it_was_given(): void
    {
        $raid = new Raid;

        $this->assertSame($raid, (new RaidingDataChanged($raid))->model);
    }
}
