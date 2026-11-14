<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\SyncGuildMemberships;
use App\Data\GuildRosterManager\MembershipEventData;
use App\Models\Character;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class SyncGuildMembershipsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array{joined_on: ?string, left_on: ?string}>
     */
    private function periods(Character $character): array
    {
        return $character->guildMemberships()
            ->orderByRaw('joined_on is null desc')
            ->orderBy('joined_on')
            ->get()
            ->map(fn ($m) => [
                'joined_on' => $m->joined_on?->toDateString(),
                'left_on' => $m->left_on?->toDateString(),
            ])
            ->all();
    }

    #[Test]
    public function it_creates_an_open_period_for_a_single_join(): void
    {
        $character = Character::factory()->create();

        SyncGuildMemberships::run($character, [new MembershipEventData('joined', '2026-01-05')]);

        $this->assertSame([['joined_on' => '2026-01-05', 'left_on' => null]], $this->periods($character));
        $this->assertTrue($character->guildMemberships()->sole()->verified);
    }

    #[Test]
    public function it_closes_a_period_on_leave(): void
    {
        $character = Character::factory()->create();

        SyncGuildMemberships::run($character, [
            new MembershipEventData('joined', '2026-01-05'),
            new MembershipEventData('left', '2026-02-10'),
        ]);

        $this->assertSame([['joined_on' => '2026-01-05', 'left_on' => '2026-02-10']], $this->periods($character));
    }

    #[Test]
    public function it_records_a_rejoin_as_a_second_period(): void
    {
        $character = Character::factory()->create();

        SyncGuildMemberships::run($character, [
            new MembershipEventData('joined', '2026-01-05'),
            new MembershipEventData('left', '2026-02-10'),
            new MembershipEventData('joined', '2026-03-01'),
        ]);

        $this->assertSame([
            ['joined_on' => '2026-01-05', 'left_on' => '2026-02-10'],
            ['joined_on' => '2026-03-01', 'left_on' => null],
        ], $this->periods($character));
    }

    #[Test]
    public function it_handles_newest_first_input(): void
    {
        $character = Character::factory()->create();

        SyncGuildMemberships::run($character, [
            new MembershipEventData('joined', '2026-03-01'),
            new MembershipEventData('left', '2026-02-10'),
            new MembershipEventData('joined', '2026-01-05'),
        ]);

        $this->assertSame([
            ['joined_on' => '2026-01-05', 'left_on' => '2026-02-10'],
            ['joined_on' => '2026-03-01', 'left_on' => null],
        ], $this->periods($character));
    }

    #[Test]
    public function it_creates_an_orphan_period_for_a_leave_without_a_join(): void
    {
        $character = Character::factory()->create();

        SyncGuildMemberships::run($character, [new MembershipEventData('left', '2026-02-10')]);

        $this->assertSame([['joined_on' => null, 'left_on' => '2026-02-10']], $this->periods($character));
        $this->assertTrue($character->guildMemberships()->sole()->verified);
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $character = Character::factory()->create();
        $events = [
            new MembershipEventData('left', '2025-12-01'),
            new MembershipEventData('joined', '2026-01-05'),
            new MembershipEventData('left', '2026-02-10'),
            new MembershipEventData('joined', '2026-03-01'),
        ];

        SyncGuildMemberships::run($character, $events);
        $first = $this->periods($character);
        SyncGuildMemberships::run($character, $events);

        $this->assertCount(3, $first);
        $this->assertSame($first, $this->periods($character));
    }

    #[Test]
    public function it_adopts_an_orphan_period_when_its_join_arrives_later(): void
    {
        $character = Character::factory()->create();

        SyncGuildMemberships::run($character, [new MembershipEventData('left', '2026-02-10')]);
        SyncGuildMemberships::run($character, [
            new MembershipEventData('joined', '2026-01-05'),
            new MembershipEventData('left', '2026-02-10'),
        ]);

        $this->assertSame([['joined_on' => '2026-01-05', 'left_on' => '2026-02-10']], $this->periods($character));
    }

    #[Test]
    public function it_orders_a_same_day_leave_and_rejoin(): void
    {
        $character = Character::factory()->create();

        SyncGuildMemberships::run($character, [
            new MembershipEventData('joined', '2026-02-10'),
            new MembershipEventData('left', '2026-02-10'),
            new MembershipEventData('joined', '2026-01-05'),
        ]);

        $this->assertSame([
            ['joined_on' => '2026-01-05', 'left_on' => '2026-02-10'],
            ['joined_on' => '2026-02-10', 'left_on' => null],
        ], $this->periods($character));
    }

    #[Test]
    public function it_orders_a_same_day_join_and_leave(): void
    {
        $character = Character::factory()->create();

        SyncGuildMemberships::run($character, [
            new MembershipEventData('left', '2026-02-10'),
            new MembershipEventData('joined', '2026-02-10'),
        ]);

        $this->assertSame([['joined_on' => '2026-02-10', 'left_on' => '2026-02-10']], $this->periods($character));
    }
}
