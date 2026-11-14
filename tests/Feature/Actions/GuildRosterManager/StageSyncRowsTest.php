<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\StageSyncRows;
use App\Data\GuildRosterManager\AltGroupData;
use App\Data\GuildRosterManager\BanData;
use App\Data\GuildRosterManager\MemberData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Models\GuildRosterManager\SyncRow;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class StageSyncRowsTest extends TestCase
{
    use RefreshDatabase;

    private Upload $sync;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sync = Upload::factory()->staging()->create();
    }

    #[Test]
    public function it_stages_member_rows_keyed_by_guid(): void
    {
        $count = StageSyncRows::run($this->sync, RosterEntryKind::Member, [$this->member()]);

        $this->assertSame(1, $count);
        $row = SyncRow::sole();
        $this->assertSame($this->sync->id, $row->upload_id);
        $this->assertSame(RosterEntryKind::Member, $row->kind);
        $this->assertSame('Player-1-AAAA', $row->key);

        $expected = $this->memberWire();
        unset($expected['epoch']);
        $this->assertSame($expected, json_decode($row->getRawOriginal('payload'), true));
    }

    #[Test]
    public function it_stores_the_raw_epoch_in_its_own_column(): void
    {
        StageSyncRows::run($this->sync, RosterEntryKind::Member, [$this->member(epoch: 1785657600)]);

        $this->assertSame(1785657600, SyncRow::sole()->epoch);
        $this->assertArrayNotHasKey('epoch', json_decode(SyncRow::sole()->getRawOriginal('payload'), true));
    }

    #[Test]
    public function it_stages_alt_groups_keyed_by_id(): void
    {
        StageSyncRows::run($this->sync, RosterEntryKind::AltGroup, [
            AltGroupData::from(['id' => '12', 'main' => 'Mira', 'members' => ['Player-1-AAAA'], 'epoch' => 9]),
        ]);

        $row = SyncRow::sole();
        $this->assertSame('12', $row->key);
        $this->assertSame(RosterEntryKind::AltGroup, $row->kind);
        $this->assertSame(['id' => '12', 'main' => 'Mira', 'members' => ['Player-1-AAAA']], json_decode($row->getRawOriginal('payload'), true));
    }

    #[Test]
    public function it_upserts_a_resent_batch(): void
    {
        StageSyncRows::run($this->sync, RosterEntryKind::Member, [$this->member(name: 'First')]);
        $count = StageSyncRows::run($this->sync, RosterEntryKind::Member, [$this->member(epoch: 1790000000, name: 'Second')]);

        $this->assertSame(1, $count);
        $row = SyncRow::sole();
        $this->assertSame('Second', $row->payload->name);
        $this->assertSame(1790000000, $row->epoch);
    }

    #[Test]
    #[Group('validation')]
    public function it_refuses_rows_beyond_the_kinds_limit(): void
    {
        $now = now();
        SyncRow::insert(collect(range(1, 1000))->map(fn (int $i): array => [
            'upload_id' => $this->sync->id,
            'kind' => RosterEntryKind::Member->value,
            'key' => "Player-{$i}-AAAA",
            'payload' => '{}',
            'epoch' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());

        StageSyncRows::run($this->sync, RosterEntryKind::Member, [$this->member('Player-1-AAAA')]);
        $this->assertSame(1000, SyncRow::count());

        try {
            StageSyncRows::run($this->sync, RosterEntryKind::Member, [$this->member('Player-5000-AAAA')]);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['The sync already holds the maximum number of member rows.'],
                $exception->errors()['rows'],
            );
        }

        $this->assertSame(1000, SyncRow::count());
    }

    #[Test]
    public function it_stages_bans_without_a_limit(): void
    {
        foreach (array_chunk(range(1, 1001), 250) as $chunk) {
            StageSyncRows::run($this->sync, RosterEntryKind::Ban, array_map(fn (int $i): BanData => $this->ban("Player-{$i}-AAAA"), $chunk));
        }

        $this->assertSame(1001, SyncRow::count());
    }

    // ==================== helpers ====================

    /**
     * @return array<string, mixed>
     */
    private function memberWire(string $guid = 'Player-1-AAAA', int $epoch = 1785657600, string $name = 'Mira'): array
    {
        return [
            'guid' => $guid,
            'name' => $name,
            'rankIndex' => 3,
            'level' => 60,
            'class' => 'WARLOCK',
            'race' => 'Scourge',
            'altGroup' => '12',
            'history' => [['type' => 'joined', 'date' => '2026-08-02']],
            'epoch' => $epoch,
        ];
    }

    private function member(string $guid = 'Player-1-AAAA', int $epoch = 1785657600, string $name = 'Mira'): MemberData
    {
        return MemberData::from($this->memberWire($guid, $epoch, $name));
    }

    private function ban(string $guid): BanData
    {
        return BanData::from([
            'guid' => $guid, 'name' => 'Bad', 'unbanned' => false, 'bannedAt' => null,
            'bannedBy' => null, 'reason' => null, 'epoch' => 5,
        ]);
    }
}
