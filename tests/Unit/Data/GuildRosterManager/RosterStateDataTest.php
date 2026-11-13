<?php

namespace Tests\Unit\Data\GuildRosterManager;

use App\Data\GuildRosterManager\RosterStateData;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class RosterStateDataTest extends TestCase
{
    #[Test]
    public function it_maps_camel_case_input_and_casts_the_commit_date_to_carbon(): void
    {
        $state = RosterStateData::from([
            'slug' => 'anniversary',
            'revision' => 7,
            'committedAt' => '2026-10-01T18:30:00+00:00',
            'fingerprints' => [
                'members' => ['Thrall-Thunderstrike' => 'abc'],
                'leavers' => [],
                'altGroups' => [],
                'ranks' => 'def',
            ],
            'banAccess' => ['create' => true, 'update' => false],
        ]);

        $this->assertSame('anniversary', $state->slug);
        $this->assertSame(7, $state->revision);
        $this->assertInstanceOf(Carbon::class, $state->committed_at);
        $this->assertTrue($state->committed_at->equalTo(Carbon::parse('2026-10-01 18:30:00', 'UTC')));
        $this->assertSame(['Thrall-Thunderstrike' => 'abc'], $state->fingerprints['members']);
        $this->assertSame(['create' => true, 'update' => false], $state->ban_access);
    }

    #[Test]
    public function it_accepts_a_null_commit_date_for_a_roster_that_has_never_been_committed(): void
    {
        $state = RosterStateData::from([
            'slug' => 'anniversary',
            'revision' => 0,
            'committedAt' => null,
            'fingerprints' => ['members' => [], 'leavers' => [], 'altGroups' => [], 'ranks' => null],
            'banAccess' => ['create' => false, 'update' => false],
        ]);

        $this->assertNull($state->committed_at);
    }

    #[Test]
    public function it_serialises_to_camel_case_keys_with_an_atom_date_and_untouched_fingerprint_groups(): void
    {
        $state = new RosterStateData(
            slug: 'anniversary',
            revision: 7,
            committed_at: Carbon::parse('2026-10-01 18:30:00', 'UTC'),
            fingerprints: [
                'members' => ['Thrall-Thunderstrike' => 'abc'],
                'leavers' => [],
                'altGroups' => ['group-1' => 'ghi'],
                'bans' => ['Garrosh-Thunderstrike' => 'jkl'],
                'ranks' => 'def',
            ],
            ban_access: ['create' => true, 'update' => true],
        );

        $this->assertSame([
            'slug' => 'anniversary',
            'revision' => 7,
            'committedAt' => '2026-10-01T18:30:00+00:00',
            'fingerprints' => [
                'members' => ['Thrall-Thunderstrike' => 'abc'],
                'leavers' => [],
                'altGroups' => ['group-1' => 'ghi'],
                'bans' => ['Garrosh-Thunderstrike' => 'jkl'],
                'ranks' => 'def',
            ],
            'banAccess' => ['create' => true, 'update' => true],
        ], $state->toArray());
    }
}
