<?php

namespace Tests\Unit\Data\GuildRosterManager;

use App\Data\GuildRosterManager\RosterRemovalsData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class RosterRemovalsDataTest extends TestCase
{
    #[Test]
    public function it_maps_camel_case_input_and_serialises_with_the_same_keys(): void
    {
        $removals = RosterRemovalsData::from($this->input());

        $this->assertSame(['Player-1-0A'], $removals->members);
        $this->assertSame(['Player-1-0B'], $removals->leavers);
        $this->assertSame(['12'], $removals->alt_groups);
        $this->assertSame(['Player-1-0C'], $removals->bans);
        $this->assertSame($this->input(), $removals->toArray());
    }

    #[Test]
    public function it_defaults_every_kind_to_an_empty_list(): void
    {
        $removals = RosterRemovalsData::from([]);

        $this->assertSame([
            'members' => [],
            'leavers' => [],
            'altGroups' => [],
            'bans' => [],
        ], $removals->toArray());
    }

    #[Test]
    public function it_returns_the_keys_for_each_kind(): void
    {
        $removals = RosterRemovalsData::from($this->input());

        $this->assertSame(['Player-1-0A'], $removals->keysFor(RosterEntryKind::Member));
        $this->assertSame(['Player-1-0B'], $removals->keysFor(RosterEntryKind::Leaver));
        $this->assertSame(['12'], $removals->keysFor(RosterEntryKind::AltGroup));
        $this->assertSame(['Player-1-0C'], $removals->keysFor(RosterEntryKind::Ban));
    }

    // ==================== helpers ====================

    /**
     * @return array<string, array<int, string>>
     */
    private function input(): array
    {
        return [
            'members' => ['Player-1-0A'],
            'leavers' => ['Player-1-0B'],
            'altGroups' => ['12'],
            'bans' => ['Player-1-0C'],
        ];
    }
}
