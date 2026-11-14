<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\ListExistingRosterKeys;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\RosterEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('characters')]
class ListExistingRosterKeysTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_the_given_keys_that_hold_an_entry_of_the_kind_in_the_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        RosterEntry::factory()->for($gameVersion)->create(['kind' => RosterEntryKind::Ban, 'key' => 'Player-1-0A']);
        RosterEntry::factory()->for($gameVersion)->create(['kind' => RosterEntryKind::Ban, 'key' => 'Player-1-0B']);

        $keys = ListExistingRosterKeys::run($gameVersion->id, RosterEntryKind::Ban, collect(['Player-1-0A', 'Player-1-0B', 'Player-1-0C']));

        $this->assertEqualsCanonicalizing(['Player-1-0A', 'Player-1-0B'], $keys->all());
    }

    #[Test]
    public function it_ignores_entries_of_the_given_keys_outside_the_requested_set(): void
    {
        $gameVersion = GameVersion::factory()->create();
        RosterEntry::factory()->for($gameVersion)->create(['kind' => RosterEntryKind::Ban, 'key' => 'Player-1-0A']);
        RosterEntry::factory()->for($gameVersion)->create(['kind' => RosterEntryKind::Ban, 'key' => 'Player-1-0B']);

        $keys = ListExistingRosterKeys::run($gameVersion->id, RosterEntryKind::Ban, collect(['Player-1-0A']));

        $this->assertSame(['Player-1-0A'], $keys->all());
    }

    #[Test]
    public function it_ignores_an_entry_of_another_kind(): void
    {
        $gameVersion = GameVersion::factory()->create();
        RosterEntry::factory()->for($gameVersion)->create(['kind' => RosterEntryKind::Member, 'key' => 'Player-1-0A']);

        $keys = ListExistingRosterKeys::run($gameVersion->id, RosterEntryKind::Ban, collect(['Player-1-0A']));

        $this->assertSame([], $keys->all());
    }

    #[Test]
    public function it_ignores_an_entry_in_another_game_version(): void
    {
        $gameVersion = GameVersion::factory()->create();
        RosterEntry::factory()->for(GameVersion::factory())->create(['kind' => RosterEntryKind::Ban, 'key' => 'Player-1-0A']);

        $keys = ListExistingRosterKeys::run($gameVersion->id, RosterEntryKind::Ban, collect(['Player-1-0A']));

        $this->assertSame([], $keys->all());
    }
}
