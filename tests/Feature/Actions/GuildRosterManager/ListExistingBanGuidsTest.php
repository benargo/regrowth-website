<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\ListExistingBanGuids;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Ban;
use App\Models\GuildRosterManager\RosterEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class ListExistingBanGuidsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_a_guid_with_a_ban_roster_entry(): void
    {
        $gameVersion = GameVersion::factory()->create();
        RosterEntry::factory()->for($gameVersion)->create(['kind' => RosterEntryKind::Ban, 'key' => 'Player-1-0A']);

        $guids = ListExistingBanGuids::run($gameVersion->id, collect(['Player-1-0A', 'Player-1-0B']));

        $this->assertSame(['Player-1-0A'], $guids->all());
    }

    #[Test]
    public function it_lists_a_guid_with_only_a_ban_log_row(): void
    {
        $gameVersion = GameVersion::factory()->create();
        Ban::factory()->for($gameVersion)->create(['grm_guid' => 'Player-1-0A']);

        $guids = ListExistingBanGuids::run($gameVersion->id, collect(['Player-1-0A', 'Player-1-0B']));

        $this->assertSame(['Player-1-0A'], $guids->all());
    }

    #[Test]
    public function it_lists_a_guid_with_both_a_roster_entry_and_a_log_row_once(): void
    {
        $gameVersion = GameVersion::factory()->create();
        RosterEntry::factory()->for($gameVersion)->create(['kind' => RosterEntryKind::Ban, 'key' => 'Player-1-0A']);
        Ban::factory()->for($gameVersion)->create(['grm_guid' => 'Player-1-0A']);

        $guids = ListExistingBanGuids::run($gameVersion->id, collect(['Player-1-0A']));

        $this->assertSame(['Player-1-0A'], $guids->all());
    }

    #[Test]
    public function it_ignores_a_roster_entry_of_another_kind(): void
    {
        $gameVersion = GameVersion::factory()->create();
        RosterEntry::factory()->for($gameVersion)->create(['kind' => RosterEntryKind::Member, 'key' => 'Player-1-0A']);

        $guids = ListExistingBanGuids::run($gameVersion->id, collect(['Player-1-0A']));

        $this->assertSame([], $guids->all());
    }

    #[Test]
    public function it_ignores_rows_in_another_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        $otherVersion = GameVersion::factory()->create();
        RosterEntry::factory()->for($otherVersion)->create(['kind' => RosterEntryKind::Ban, 'key' => 'Player-1-0A']);
        Ban::factory()->for($otherVersion)->create(['grm_guid' => 'Player-1-0A']);

        $guids = ListExistingBanGuids::run($gameVersion->id, collect(['Player-1-0A']));

        $this->assertSame([], $guids->all());
    }
}
