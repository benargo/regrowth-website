<?php

namespace Tests\Unit\Data\GuildRosterManager;

use App\Data\GuildRosterManager\MembershipEventData;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class MembershipEventDataTest extends TestCase
{
    #[Test]
    public function it_maps_input_and_serialises_with_the_same_keys(): void
    {
        $event = MembershipEventData::from(['type' => 'joined', 'date' => '2026-08-02']);

        $this->assertSame(['type' => 'joined', 'date' => '2026-08-02'], $event->toArray());
    }
}
