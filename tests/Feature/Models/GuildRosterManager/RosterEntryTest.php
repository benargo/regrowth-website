<?php

namespace Tests\Feature\Models\GuildRosterManager;

use App\Data\GuildRosterManager\BanData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Models\GuildRosterManager\RosterEntry;
use App\Models\GuildRosterManager\SyncRow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class RosterEntryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    #[DataProvider('kinds')]
    public function it_hydrates_a_factory_payload_matching_an_overridden_kind(RosterEntryKind $kind): void
    {
        $payload = RosterEntry::factory()->create(['kind' => $kind, 'key' => 'K-1'])->fresh()->payload;

        $this->assertInstanceOf($kind->dataClass(), $payload);
        $this->assertSame('K-1', $payload->key());
    }

    #[Test]
    public function it_stores_the_payload_without_epoch(): void
    {
        $entry = RosterEntry::factory()->create(['epoch' => 1700000000])->fresh();

        $this->assertArrayNotHasKey('epoch', json_decode($entry->getRawOriginal('payload'), true));
        $this->assertSame(1700000000, $entry->payload->epoch);
    }

    #[Test]
    public function it_reflects_a_changed_epoch_on_the_next_read(): void
    {
        $entry = RosterEntry::factory()->create(['epoch' => 100])->fresh();
        $this->assertSame(100, $entry->payload->epoch);

        $entry->epoch = 200;

        $this->assertSame(200, $entry->payload->epoch);
    }

    #[Test]
    public function it_hydrates_a_sync_row_payload(): void
    {
        $payload = SyncRow::factory()->create(['kind' => RosterEntryKind::Ban, 'key' => 'Player-1-AAAA'])->fresh()->payload;

        $this->assertInstanceOf(BanData::class, $payload);
        $this->assertSame('Player-1-AAAA', $payload->key());
    }

    /**
     * @return array<string, array{RosterEntryKind}>
     */
    public static function kinds(): array
    {
        return collect(RosterEntryKind::cases())->mapWithKeys(fn (RosterEntryKind $kind): array => [$kind->value => [$kind]])->all();
    }
}
