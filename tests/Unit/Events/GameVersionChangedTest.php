<?php

namespace Tests\Unit\Events;

use App\Contracts\Events\FlushesGameVersionsCache;
use App\Contracts\Events\FlushesRaidingCache;
use App\Events\GameVersionChanged;
use App\Models\GameVersion;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('raiding')]
class GameVersionChangedTest extends TestCase
{
    #[Group('contract')]
    #[Test]
    public function it_flushes_the_game_versions_cache(): void
    {
        $this->assertInstanceOf(FlushesGameVersionsCache::class, new GameVersionChanged(new GameVersion));
    }

    #[Group('contract')]
    #[Test]
    public function it_flushes_the_raiding_cache(): void
    {
        $this->assertInstanceOf(FlushesRaidingCache::class, new GameVersionChanged(new GameVersion));
    }
}
