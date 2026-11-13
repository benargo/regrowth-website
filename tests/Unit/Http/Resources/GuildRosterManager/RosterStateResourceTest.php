<?php

namespace Tests\Unit\Http\Resources\GuildRosterManager;

use App\Data\GuildRosterManager\RosterStateData;
use App\Http\Resources\GuildRosterManager\RosterStateResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class RosterStateResourceTest extends TestCase
{
    #[Test]
    #[Group('resource')]
    public function it_returns_the_roster_state_shape_identified_by_the_slug(): void
    {
        $state = new RosterStateData(
            slug: 'forever',
            revision: 2,
            committed_at: Carbon::parse('2026-11-13 12:00:00', 'Europe/London'),
            fingerprints: [
                'members' => ['Player-1-A' => 'fp-m'],
                'leavers' => [],
                'altGroups' => [],
                'ranks' => 'ranks-fp',
            ],
            ban_access: ['create' => false, 'update' => false],
        );

        $this->assertEquals([
            'id' => 'forever',
            'type' => 'roster_states',
            'attributes' => (object) [
                'revision' => 2,
                'committedAt' => '2026-11-13T12:00:00Z',
                'fingerprints' => [
                    'members' => (object) ['Player-1-A' => 'fp-m'],
                    'leavers' => (object) [],
                    'altGroups' => (object) [],
                    'ranks' => 'ranks-fp',
                ],
                'banAccess' => ['create' => false, 'update' => false],
            ],
        ], (new RosterStateResource($state))->resolve(new Request)['data']);
    }

    #[Test]
    #[Group('resource')]
    public function it_returns_a_null_committed_at_before_any_commit(): void
    {
        $state = new RosterStateData(
            slug: 'forever',
            revision: 0,
            committed_at: null,
            fingerprints: ['members' => [], 'leavers' => [], 'altGroups' => [], 'ranks' => null],
            ban_access: ['create' => false, 'update' => false],
        );

        $this->assertNull((new RosterStateResource($state))->resolve(new Request)['data']['attributes']->committedAt);
    }
}
