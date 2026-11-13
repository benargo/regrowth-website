<?php

namespace Tests\Feature\Models\GuildRosterManager;

use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\RosterEntry;
use App\Models\GuildRosterManager\RosterRevision;
use App\Models\GuildRosterManager\SyncRow;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class RosterStateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function current_roster_revision_is_the_highest_revision(): void
    {
        $gameVersion = GameVersion::factory()->create();

        foreach ([1, 3, 2] as $revision) {
            RosterRevision::factory()->for($gameVersion)->create(['revision' => $revision]);
        }

        $this->assertSame(3, $gameVersion->currentRosterRevision->revision);
    }

    #[Test]
    public function it_refuses_a_duplicate_revision_for_a_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        RosterRevision::factory()->for($gameVersion)->create(['revision' => 1]);

        $this->expectException(UniqueConstraintViolationException::class);

        RosterRevision::factory()->for($gameVersion)->create(['revision' => 1]);
    }

    #[Test]
    public function it_refuses_a_duplicate_entry_key_per_kind(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $guid = 'Player-1-ABCDEF01';

        RosterEntry::factory()->for($gameVersion)->create(['kind' => RosterEntryKind::Member, 'key' => $guid]);
        RosterEntry::factory()->for($gameVersion)->create(['kind' => RosterEntryKind::Leaver, 'key' => $guid]);

        $this->assertSame(2, RosterEntry::count());

        $this->expectException(UniqueConstraintViolationException::class);

        RosterEntry::factory()->for($gameVersion)->create(['kind' => RosterEntryKind::Member, 'key' => $guid]);
    }

    #[Test]
    public function deleting_an_upload_deletes_its_sync_rows(): void
    {
        $upload = Upload::factory()->staging()->create();
        SyncRow::factory()->for($upload)->count(2)->create();

        $this->assertSame(2, $upload->syncRows()->count());

        $upload->delete();

        $this->assertSame(0, SyncRow::count());
    }

    #[Test]
    public function each_model_declares_its_grm_table(): void
    {
        $this->assertSame('grm_roster_revisions', (new RosterRevision)->getTable());
        $this->assertSame('grm_roster_entries', (new RosterEntry)->getTable());
        $this->assertSame('grm_sync_rows', (new SyncRow)->getTable());
    }
}
