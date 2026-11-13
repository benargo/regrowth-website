<?php

namespace Tests\Unit\Data\GuildRosterManager;

use App\Data\GuildRosterManager\RanksData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class RanksDataTest extends TestCase
{
    #[Test]
    public function it_maps_input_and_serialises_with_the_same_keys(): void
    {
        $ranks = RanksData::from(['ranks' => ['Guild Master', 'Officer', 'Raider']]);

        $this->assertSame(['ranks' => ['Guild Master', 'Officer', 'Raider']], $ranks->toArray());
    }

    #[Test]
    public function it_reindexes_rank_names_from_zero_in_the_fingerprint_payload(): void
    {
        $ranks = new RanksData([3 => 'Guild Master', 7 => 'Officer']);

        $this->assertSame([0 => 'Guild Master', 1 => 'Officer'], $ranks->fingerprintPayload());
    }
}
