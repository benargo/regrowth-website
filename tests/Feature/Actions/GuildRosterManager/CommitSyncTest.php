<?php

namespace Tests\Feature\Actions\GuildRosterManager;

use App\Actions\GuildRosterManager\CommitSync;
use App\Actions\GuildRosterManager\ComputeFingerprint;
use App\Data\GuildRosterManager\CommitSyncResultData;
use App\Data\GuildRosterManager\RanksData;
use App\Data\GuildRosterManager\RosterRemovalsData;
use App\Data\GuildRosterManager\SyncChangesData;
use App\Enums\GuildRosterManager\CommitSyncStatus;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Enums\GuildRosterManager\SyncRowSkipReason;
use App\Enums\GuildRosterManager\UploadOutcome;
use App\Events\DesktopClient\SyncCommitted;
use App\Events\DesktopClient\SyncCommitting;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Jobs\GuildRosterManager\ReindexCharacterRanks;
use App\Jobs\RefreshGuildRosterAfterEditing;
use App\Models\Character;
use App\Models\DesktopClient;
use App\Models\DiscordRole;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\GuildRosterManager\Ban;
use App\Models\GuildRosterManager\RosterEntry;
use App\Models\GuildRosterManager\RosterRevision;
use App\Models\GuildRosterManager\SyncRow;
use App\Models\GuildRosterManager\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

#[Group('characters')]
class CommitSyncTest extends TestCase
{
    use RefreshDatabase;

    private const RANKS = ['Guild Master', 'Officer', 'Veteran', 'Raider', 'Member', 'Initiate'];

    private const UPLOADER_PERMISSIONS = ['use-desktop-client', 'upload-grm-rosters', 'create-grm-bans', 'update-grm-bans'];

    private const CLOCK_TOLERANCE = 60;

    private User $uploader;

    private GameVersion $forever;

    private DesktopClient $desktopClient;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->freezeSecond();
        config(['services.grm.clock_tolerance_seconds' => self::CLOCK_TOLERANCE]);
        Event::fake([SyncCommitting::class, SyncCommitted::class]);

        $this->uploader = $this->userWithPermissions(self::UPLOADER_PERMISSIONS);
        $this->forever = GameVersion::factory()->withSurnames()->create([
            'slug' => 'forever',
            'blizzard_namespace' => BlizzardNamespace::FOREVER,
            'realm' => null,
        ]);
        $this->desktopClient = DesktopClient::factory()->create(['user_id' => $this->uploader->id]);

        Bus::fake([RefreshGuildRosterAfterEditing::class, ReindexCharacterRanks::class]);

        foreach (self::RANKS as $sortOrder => $name) {
            GuildRank::factory()->for($this->forever)->create(['sort_order' => $sortOrder, 'name' => $name]);
        }
    }

    // ==================== ranks ====================

    #[Test]
    public function it_renames_the_site_ranks_from_the_committed_ranks(): void
    {
        $ids = $this->forever->guildRanks()->ordered()->pluck('id')->all();
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Member, $this->fixture('members')[0]);
        $ranks = ['Guild Master', 'Officer', 'Veteran', 'Core raider', 'Member', 'Initiate'];

        $this->commit($sync, $ranks);

        $this->assertSame($ranks, $this->forever->guildRanks()->ordered()->pluck('name')->all());
        $this->assertSame($ids, $this->forever->guildRanks()->ordered()->pluck('id')->all());
        Bus::assertNotDispatched(ReindexCharacterRanks::class);
    }

    #[Test]
    public function it_reapplies_the_previous_ranks_over_an_officer_edit(): void
    {
        $this->revision(1);
        $this->forever->guildRanks()->where('sort_order', 1)->update(['name' => 'Lieutenant']);
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Member, $this->fixture('members')[0]);

        $this->commit($sync);

        $this->assertSame(self::RANKS, $this->forever->guildRanks()->ordered()->pluck('name')->all());
    }

    #[Test]
    public function it_queues_a_rank_reindex_when_ranks_were_added_or_removed(): void
    {
        $this->revision(1);
        $this->forever->guildRanks()->where('sort_order', 5)->delete();
        $sync = $this->openSync();

        $result = $this->commit($sync);

        $this->assertSame(1, $result->revision);
        $this->assertSame(UploadOutcome::NoChanges, $sync->fresh()->outcome);
        $this->assertSame(self::RANKS, $this->forever->guildRanks()->ordered()->pluck('name')->all());
        Bus::assertDispatchedTimes(ReindexCharacterRanks::class, 1);
        Bus::assertDispatched(ReindexCharacterRanks::class, fn (ReindexCharacterRanks $job): bool => $job->gameVersionId === $this->forever->id);
    }

    // ==================== apply ====================

    #[Test]
    public function it_applies_staged_rows_and_creates_revision_one(): void
    {
        $sync = $this->openSync();
        $this->stageFixture($sync);

        $result = $this->commit($sync, self::RANKS);

        $this->assertSame(CommitSyncStatus::Committed, $result->status);
        $this->assertSame(1, $result->revision);
        $this->assertTrue($result->sync->is($sync));

        foreach ($this->fixtureRows() as [$kind, $row]) {
            $entry = $this->entry($kind, $row[$kind->keyAttribute()]);
            $this->assertSame($this->fingerprint($kind, $row), $entry->fingerprint);
            $this->assertSame(1, $entry->revision);
        }

        $revision = RosterRevision::sole();
        $this->assertSame(1, $revision->revision);
        $this->assertSame($this->forever->id, $revision->game_version_id);
        $this->assertSame($sync->id, $revision->upload_id);
        $this->assertTrue($revision->committed_at->equalTo(now()));
        $this->assertSame(self::RANKS, $revision->ranks);
        $this->assertSame(ComputeFingerprint::run(new RanksData(self::RANKS)), $revision->ranks_fingerprint);
    }

    #[Test]
    public function it_stores_the_raw_epoch_on_each_entry(): void
    {
        $sync = $this->openSync();
        $this->stageFixture($sync);

        $this->commit($sync, self::RANKS);

        foreach ($this->fixtureRows() as [$kind, $row]) {
            $this->assertSame($row['epoch'], $this->entry($kind, $row[$kind->keyAttribute()])->epoch);
        }
    }

    #[Test]
    public function it_stores_a_committed_entrys_payload_exactly_as_staged(): void
    {
        $sync = $this->openSync();
        $row = $this->fixture('members')[0];
        $staged = $this->stage($sync, RosterEntryKind::Member, $row);
        $stagedPayload = json_decode($staged->getRawOriginal('payload'), true);

        $this->commit($sync, self::RANKS);

        $entry = $this->entry(RosterEntryKind::Member, $row['guid']);
        $this->assertSame($stagedPayload, json_decode($entry->getRawOriginal('payload'), true));
    }

    #[Test]
    public function it_deletes_the_staged_rows(): void
    {
        $applied = $this->openSync();
        $this->stageFixture($applied);

        $this->commit($applied, self::RANKS);

        $this->assertSame(0, SyncRow::where('upload_id', $applied->id)->count());

        $noChanges = $this->openSync();
        $this->stage($noChanges, RosterEntryKind::Member, $this->fixture('members')[0]);

        $result = $this->commit($noChanges);

        $this->assertSame(UploadOutcome::NoChanges, $result->sync->outcome);
        $this->assertSame(0, SyncRow::where('upload_id', $noChanges->id)->count());
    }

    #[Test]
    public function it_keeps_the_previous_ranks_when_none_are_sent(): void
    {
        $previous = $this->revision(1);
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Member, $this->fixture('members')[0]);

        $result = $this->commit($sync);

        $this->assertSame(2, $result->revision);
        $revision = RosterRevision::where('revision', 2)->sole();
        $this->assertSame(self::RANKS, $revision->ranks);
        $this->assertSame($previous->ranks_fingerprint, $revision->ranks_fingerprint);
    }

    #[Test]
    #[Group('validation')]
    public function it_requires_ranks_on_the_first_sync(): void
    {
        $sync = $this->openSync();
        $this->stageFixture($sync);

        try {
            $this->commit($sync);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(['ranks' => ['The ranks are required for the first sync.']], $exception->errors());
        }

        $this->assertSame(0, RosterEntry::count());
        $this->assertSame(0, RosterRevision::count());
        $this->assertSame(UploadOutcome::Staging, $sync->fresh()->outcome);
        $this->assertSame(6, SyncRow::where('upload_id', $sync->id)->count());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_blank_name_in_the_previous_ranks_it_would_reapply(): void
    {
        $this->revision(1, ['Guild Master', '  ']);
        $sync = $this->openSync();
        $this->stageFixture($sync);

        try {
            $this->commit($sync);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(['ranks.1' => ['The rank name must not be blank.']], $exception->errors());
        }

        $this->assertSame(1, RosterRevision::count());
        $this->assertSame(UploadOutcome::Staging, $sync->fresh()->outcome);
    }

    #[Test]
    public function it_records_member_count_and_received(): void
    {
        $sync = $this->openSync();
        $this->stageFixture($sync);

        $this->commit($sync, self::RANKS);

        $sync->refresh();
        $this->assertSame(UploadOutcome::Received, $sync->outcome);
        $this->assertSame(1, $sync->revision);
        $this->assertSame(3, $sync->member_count);
        $this->assertNull($sync->expires_at);
        $this->assertNull($sync->ticket_expires_at);
        $this->assertTrue($this->desktopClient->fresh()->last_upload_at->equalTo(now()));
    }

    #[Test]
    public function it_records_no_changes_without_a_new_revision(): void
    {
        $this->revision(1);
        $member = $this->fixture('members')[0];
        $this->storedEntry(RosterEntryKind::Member, $member);
        $empty = $this->openSync();

        $result = $this->commit($empty);

        $this->assertSame(CommitSyncStatus::Committed, $result->status);
        $this->assertSame(1, $result->revision);
        $this->assertSame(UploadOutcome::NoChanges, $empty->fresh()->outcome);
        $this->assertSame(1, $empty->fresh()->revision);
        $this->assertSame(1, $empty->fresh()->member_count);
        Event::assertDispatchedTimes(SyncCommitted::class, 1);

        $noOp = $this->openSync();
        $this->stage($noOp, RosterEntryKind::Member, $member);

        $result = $this->commit($noOp);

        $this->assertSame(1, $result->revision);
        $this->assertSame(UploadOutcome::NoChanges, $noOp->fresh()->outcome);
        $this->assertSame(1, RosterRevision::count());
        Event::assertDispatchedTimes(SyncCommitted::class, 2);
        Event::assertNotDispatched(SyncCommitting::class);
    }

    #[Test]
    public function it_fires_sync_committing_after_the_transaction(): void
    {
        $this->revision(1);
        $departing = 'Player-5826-0A1B2C99';
        $this->storedEntry(RosterEntryKind::Member, $this->memberRow(['guid' => $departing, 'epoch' => 1000]));
        $this->storedEntry(RosterEntryKind::Leaver, $this->leaverRow(['guid' => $departing, 'epoch' => 2000]));
        $this->storedEntry(RosterEntryKind::AltGroup, $this->altGroupRow(['main' => 'Old Main', 'members' => ['Player-5826-0A1B2C3D'], 'epoch' => 1000]));
        $sync = $this->openSync();
        $this->stageFixture($sync);

        DB::transaction(function () use ($sync, $departing): void {
            $this->commit($sync, removed: new RosterRemovalsData(members: [$departing]));

            Event::assertNotDispatched(SyncCommitting::class);
        });

        Event::assertDispatchedTimes(SyncCommitting::class, 1);
        Event::assertNotDispatched(SyncCommitted::class);
        $event = Event::dispatched(SyncCommitting::class)->sole()[0];
        $this->assertTrue($event->sync->is($sync));
        $this->assertInstanceOf(SyncChangesData::class, $event->changes);
        $this->assertSame(array_column($this->fixture('members'), 'guid'), $event->changes->members);
        $this->assertSame(array_column($this->fixture('leavers'), 'guid'), $event->changes->leavers);
        $this->assertSame(['12'], $event->changes->alt_groups);
        $this->assertSame([$departing], $event->changes->departed_members);
        $this->assertSame(
            ['12' => ['id' => '12', 'main' => 'Old Main', 'members' => ['Player-5826-0A1B2C3D']]],
            $event->changes->previous_alt_groups,
        );
        $this->assertSame([Ban::sole()->id], $event->changes->ban_ids);
    }

    // ==================== removals ====================

    #[Test]
    public function it_drops_a_removed_leaver_from_state_only(): void
    {
        $this->revision(1);
        $leaver = $this->fixture('leavers')[0];
        $this->storedEntry(RosterEntryKind::Leaver, $leaver);
        Character::factory()->forGameVersion($this->forever)->create(['name' => 'Gone', 'surname' => 'Fishing']);
        $characters = Character::all()->toArray();
        $sync = $this->openSync();

        $result = $this->commit($sync, removed: new RosterRemovalsData(leavers: [$leaver['guid']]));

        $this->assertSame(2, $result->revision);
        $this->assertNull($this->entry(RosterEntryKind::Leaver, $leaver['guid']));
        $this->assertSame($characters, Character::all()->toArray());
    }

    #[Test]
    public function it_departs_a_removed_member_with_a_leaver_entry(): void
    {
        $this->revision(1);
        $guid = 'Player-5826-0A1B2C99';
        $this->storedEntry(RosterEntryKind::Member, $this->memberRow(['guid' => $guid, 'epoch' => 1000]));
        $this->storedEntry(RosterEntryKind::Leaver, $this->leaverRow(['guid' => $guid, 'epoch' => 2000]));
        $sync = $this->openSync();

        $this->commit($sync, removed: new RosterRemovalsData(members: [$guid]));

        $this->assertNull($this->entry(RosterEntryKind::Member, $guid));
        $this->assertNotNull($this->entry(RosterEntryKind::Leaver, $guid));
        $this->assertSame([$guid], $this->committingChanges()->departed_members);
    }

    #[Test]
    public function it_ignores_the_removal_of_a_guid_the_state_does_not_have(): void
    {
        $this->revision(1);
        $sync = $this->openSync();
        $unknown = 'Player-5826-0A1B2CFF';

        $result = $this->commit($sync, removed: new RosterRemovalsData(members: [$unknown], leavers: [$unknown], alt_groups: ['99'], bans: [$unknown]));

        $this->assertSame(1, $result->revision);
        $this->assertSame(UploadOutcome::NoChanges, $result->sync->outcome);
        $this->assertSame(1, RosterRevision::count());
    }

    #[Test]
    #[Group('validation')]
    public function it_rejects_a_key_both_staged_and_removed(): void
    {
        $sync = $this->openSync();
        $leaver = $this->fixture('leavers')[0];
        $this->stage($sync, RosterEntryKind::Leaver, $leaver);

        try {
            $this->commit($sync, self::RANKS, new RosterRemovalsData(leavers: ['Player-5826-0A1B2CFF', $leaver['guid']]));
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(['removed.leavers.1'], array_keys($exception->errors()));
        }

        $this->assertSame(0, RosterEntry::count());
        $this->assertSame(UploadOutcome::Staging, $sync->fresh()->outcome);
        $this->assertSame(1, SyncRow::where('upload_id', $sync->id)->count());
    }

    #[Test]
    public function it_keeps_the_leaver_entry_of_a_departed_member_removed_from_both_lists(): void
    {
        $this->revision(1);
        $guid = 'Player-5826-0A1B2C99';
        $this->storedEntry(RosterEntryKind::Member, $this->memberRow(['guid' => $guid, 'epoch' => 1000]));
        $this->storedEntry(RosterEntryKind::Leaver, $this->leaverRow(['guid' => $guid, 'epoch' => 2000]));
        $sync = $this->openSync();

        $this->commit($sync, removed: new RosterRemovalsData(members: [$guid], leavers: [$guid]));

        $this->assertNull($this->entry(RosterEntryKind::Member, $guid));
        $this->assertNotNull($this->entry(RosterEntryKind::Leaver, $guid));
        $this->assertSame([$guid], $this->committingChanges()->departed_members);
    }

    // ==================== bans ====================

    #[Test]
    public function it_records_a_new_ban_linked_to_its_character_and_user(): void
    {
        $player = User::factory()->create();
        $character = Character::factory()->forGameVersion($this->forever)->forUser($player)->create(['name' => 'Bad', 'surname' => 'Apple']);
        $sync = $this->openSync();
        $row = $this->banRow();
        $this->stage($sync, RosterEntryKind::Ban, $row);

        $this->commit($sync, self::RANKS);

        $ban = Ban::sole();
        $this->assertSame($this->forever->id, $ban->game_version_id);
        $this->assertSame($row['guid'], $ban->grm_guid);
        $this->assertSame('Bad Apple', $ban->name);
        $this->assertSame('Mira Ashvale', $ban->banned_by);
        $this->assertSame($row['reason'], $ban->reason);
        $this->assertSame($character->id, $ban->character_id);
        $this->assertSame($player->id, $ban->user_id);
        $this->assertNull($ban->unbanned_at);
    }

    #[Test]
    public function it_records_banned_at_from_the_raw_epoch(): void
    {
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Ban, $this->banRow(['epoch' => 1791000000, 'bannedAt' => '2026-10-03T19:00:00Z']));

        $this->commit($sync, self::RANKS);

        $this->assertSame(1791000000, Ban::sole()->banned_at->getTimestamp());
    }

    #[Test]
    public function it_unbans_when_the_ban_row_says_unbanned(): void
    {
        $this->revision(1);
        $row = $this->banRow(['epoch' => 1000]);
        $this->storedEntry(RosterEntryKind::Ban, $row);
        $ban = $this->ban($row);
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Ban, $this->banRow([
            'unbanned' => true,
            'bannedAt' => '2026-11-01T12:00:00Z',
            'bannedBy' => 'Pan Bakewell',
            'reason' => null,
            'epoch' => 2000,
        ]));

        $this->commit($sync);

        $unbanned = $ban->fresh();
        $this->assertSame(2000, $unbanned->unbanned_at->getTimestamp());
        $this->assertTrue($unbanned->banned_at->equalTo($ban->banned_at));
        $this->assertSame($ban->banned_by, $unbanned->banned_by);
        $this->assertSame($ban->reason, $unbanned->reason);
        $this->assertSame([$ban->id], $this->committingChanges()->ban_ids);
    }

    #[Test]
    public function it_unbans_at_the_current_time_when_the_unban_row_has_no_epoch(): void
    {
        $this->revision(1);
        $row = $this->banRow(['epoch' => 0]);
        $this->storedEntry(RosterEntryKind::Ban, $row);
        $ban = $this->ban($row);
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Ban, $this->banRow(['unbanned' => true, 'reason' => null, 'epoch' => 0]));

        $this->commit($sync);

        $this->assertTrue($ban->fresh()->unbanned_at->equalTo(now()));
    }

    #[Test]
    public function it_never_creates_a_ban_row_for_an_unseen_unban(): void
    {
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Ban, $this->banRow(['unbanned' => true, 'reason' => null]));

        $this->commit($sync, self::RANKS);

        $this->assertSame(0, Ban::count());
        $this->assertSame([], $this->committingChanges()->ban_ids);
    }

    #[Test]
    public function it_clears_unbanned_at_when_a_player_is_banned_again(): void
    {
        $this->revision(1);
        $row = $this->banRow(['epoch' => 1000]);
        $this->storedEntry(RosterEntryKind::Ban, $row);
        $ban = $this->ban($row, ['unbanned_at' => now()->subDay()]);
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Ban, $this->banRow(['reason' => 'Again', 'epoch' => 2000]));

        $this->commit($sync);

        $this->assertNull($ban->fresh()->unbanned_at);
        $this->assertSame('Again', $ban->fresh()->reason);
    }

    #[Test]
    public function it_stores_a_ban_reason_only_in_grm_bans(): void
    {
        $sync = $this->openSync();
        $row = $this->banRow();
        $this->stage($sync, RosterEntryKind::Ban, $row);

        $this->commit($sync, self::RANKS);

        $entry = $this->entry(RosterEntryKind::Ban, $row['guid']);
        $this->assertArrayNotHasKey('reason', json_decode($entry->getRawOriginal('payload'), true));
        $this->assertSame($this->fingerprint(RosterEntryKind::Ban, $row), $entry->fingerprint);
        $this->assertSame(0, SyncRow::count());
        $this->assertSame($row['reason'], Ban::sole()->reason);
    }

    #[Test]
    public function it_applies_a_ban_whose_only_change_is_the_reason(): void
    {
        $this->revision(1);
        $stored = $this->banRow(['reason' => 'Reason A']);
        $this->storedEntry(RosterEntryKind::Ban, $stored);
        $ban = $this->ban($stored);
        $sync = $this->openSync();
        $changed = $this->banRow(['reason' => 'Reason B']);
        $this->stage($sync, RosterEntryKind::Ban, $changed);

        $this->commit($sync);

        $this->assertSame('Reason B', $ban->fresh()->reason);
        $this->assertSame($this->fingerprint(RosterEntryKind::Ban, $changed), $this->entry(RosterEntryKind::Ban, $changed['guid'])->fingerprint);
    }

    // ==================== replay and conflicts ====================

    #[Test]
    public function it_replays_an_already_committed_sync(): void
    {
        $sync = $this->openSync();
        $this->stageFixture($sync);
        $this->commit($sync, self::RANKS);

        $result = $this->commit($sync, self::RANKS);

        $this->assertSame(CommitSyncStatus::Committed, $result->status);
        $this->assertSame(1, $result->revision);
        $this->assertSame(1, RosterRevision::count());
        Event::assertDispatchedTimes(SyncCommitting::class, 1);
        Event::assertNotDispatched(SyncCommitted::class);
    }

    #[Test]
    public function it_replays_a_committed_desktop_upload_without_a_revision_as_zero(): void
    {
        $upload = Upload::factory()->desktop()->create([
            'user_id' => $this->uploader->id,
            'game_version_id' => $this->forever->id,
            'outcome' => UploadOutcome::Received,
            'revision' => null,
        ]);

        $result = $this->commit($upload);

        $this->assertSame(CommitSyncStatus::Committed, $result->status);
        $this->assertSame(0, $result->revision);
        Event::assertNothingDispatched();
    }

    #[Test]
    public function it_supersedes_a_commit_whose_base_revision_is_stale(): void
    {
        $first = $this->openSync();
        $second = $this->openSync(user: $this->userWithPermissions(self::UPLOADER_PERMISSIONS));
        $this->stageFixture($first);
        $late = $this->memberRow(['guid' => 'Player-5826-0A1B2C99']);
        $this->stage($second, RosterEntryKind::Member, $late);
        $this->commit($first, self::RANKS);

        $result = $this->commit($second, self::RANKS);

        $this->assertSame(CommitSyncStatus::RevisionConflict, $result->status);
        $this->assertSame(1, $result->revision);
        $this->assertSame(UploadOutcome::Superseded, $second->fresh()->outcome);
        $this->assertNull($this->entry(RosterEntryKind::Member, $late['guid']));
        $this->assertSame(1, RosterRevision::count());
    }

    #[Test]
    public function it_marks_an_expired_sync_and_applies_nothing(): void
    {
        $sync = $this->openSync();
        $this->stageFixture($sync);
        $this->travel(61)->minutes();

        $result = $this->commit($sync, self::RANKS);

        $this->assertSame(CommitSyncStatus::Expired, $result->status);
        $this->assertSame(UploadOutcome::Expired, $sync->fresh()->outcome);
        $this->assertSame(0, RosterEntry::count());
        $this->assertSame(0, RosterRevision::count());
        Event::assertNothingDispatched();
    }

    #[Test]
    public function it_refuses_a_commit_whose_ticket_has_expired(): void
    {
        $sync = $this->openSync();
        $sync->forceFill(['ticket_expires_at' => now()->subSecond()])->save();
        $this->stageFixture($sync);

        $result = $this->commit($sync, self::RANKS);

        $this->assertSame(CommitSyncStatus::TicketExpired, $result->status);
        $sync->refresh();
        $this->assertSame(UploadOutcome::Expired, $sync->outcome);
        $this->assertNull($sync->expires_at);
        $this->assertNull($sync->ticket_expires_at);
        $this->assertSame(0, RosterEntry::count());
        Event::assertNothingDispatched();
    }

    // ==================== newer wins ====================

    #[Test]
    public function it_applies_a_row_newer_than_the_stored_row(): void
    {
        $this->revision(1);
        $this->storedEntry(RosterEntryKind::Member, $this->memberRow(['level' => 58, 'epoch' => 1000]));
        $sync = $this->openSync();
        $newer = $this->memberRow(['level' => 60, 'epoch' => 2000]);
        $this->stage($sync, RosterEntryKind::Member, $newer);

        $this->commit($sync);

        $entry = $this->entry(RosterEntryKind::Member, $newer['guid']);
        $this->assertSame($this->fingerprint(RosterEntryKind::Member, $newer), $entry->fingerprint);
        $this->assertSame(2000, $entry->epoch);
        $this->assertSame(2, $entry->revision);
    }

    #[Test]
    public function it_applies_a_row_with_an_equal_epoch(): void
    {
        $this->revision(1);
        $this->storedEntry(RosterEntryKind::Member, $this->memberRow(['level' => 58, 'epoch' => 2000]));
        $sync = $this->openSync();
        $levelled = $this->memberRow(['level' => 60, 'epoch' => 2000]);
        $this->stage($sync, RosterEntryKind::Member, $levelled);

        $this->commit($sync);

        $entry = $this->entry(RosterEntryKind::Member, $levelled['guid']);
        $this->assertSame(60, $entry->payload->level);
        $this->assertSame($this->fingerprint(RosterEntryKind::Member, $levelled), $entry->fingerprint);
    }

    #[Test]
    public function it_skips_and_logs_an_older_row(): void
    {
        Log::spy();
        $this->revision(1);
        $stored = $this->storedEntry(RosterEntryKind::Member, $this->memberRow(['level' => 58, 'epoch' => 2000]));
        $sync = $this->openSync();
        $older = $this->memberRow(['level' => 60, 'epoch' => 1000]);
        $this->stage($sync, RosterEntryKind::Member, $older);

        $this->commit($sync);

        $entry = $stored->fresh();
        $this->assertSame($stored->fingerprint, $entry->fingerprint);
        $this->assertSame(2000, $entry->epoch);
        $this->assertSame(1, $entry->revision);
        $this->assertSame(58, $entry->payload->level);
        $this->assertSkipped($sync, RosterEntryKind::Member, $older['guid'], SyncRowSkipReason::Older);
    }

    #[Test]
    public function it_loses_a_zero_epoch_row_to_a_later_stored_row_and_applies_against_a_stored_zero(): void
    {
        Log::spy();
        $this->revision(1);
        $later = $this->storedEntry(RosterEntryKind::Member, $this->memberRow(['guid' => 'Player-5826-0A1B2C90', 'level' => 58, 'epoch' => 2000]));
        $this->storedEntry(RosterEntryKind::Member, $this->memberRow(['guid' => 'Player-5826-0A1B2C91', 'level' => 58, 'epoch' => 0]));
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Member, $this->memberRow(['guid' => 'Player-5826-0A1B2C90', 'level' => 60, 'epoch' => 0]));
        $againstZero = $this->memberRow(['guid' => 'Player-5826-0A1B2C91', 'level' => 60, 'epoch' => 0]);
        $this->stage($sync, RosterEntryKind::Member, $againstZero);

        $this->commit($sync);

        $this->assertSame($later->fingerprint, $later->fresh()->fingerprint);
        $this->assertSame(2000, $later->fresh()->epoch);
        $this->assertSkipped($sync, RosterEntryKind::Member, 'Player-5826-0A1B2C90', SyncRowSkipReason::Older);
        $this->assertSame($this->fingerprint(RosterEntryKind::Member, $againstZero), $this->entry(RosterEntryKind::Member, 'Player-5826-0A1B2C91')->fingerprint);
    }

    #[Test]
    public function it_compares_a_leaver_row_with_the_stored_member_entry(): void
    {
        Log::spy();
        $this->revision(1);
        $guid = $this->memberRow()['guid'];
        $this->storedEntry(RosterEntryKind::Member, $this->memberRow(['epoch' => 2000]));
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Leaver, $this->leaverRow(['guid' => $guid, 'epoch' => 1500]));

        $this->commit($sync, removed: new RosterRemovalsData(members: [$guid]));

        $this->assertNull($this->entry(RosterEntryKind::Leaver, $guid));
        $this->assertNotNull($this->entry(RosterEntryKind::Member, $guid));
        $this->assertSkipped($sync, RosterEntryKind::Leaver, $guid, SyncRowSkipReason::Older);
        $this->assertSkipped($sync, RosterEntryKind::Member, $guid, SyncRowSkipReason::Older);
    }

    #[Test]
    public function it_skips_an_older_alt_group_change(): void
    {
        Log::spy();
        $this->revision(1);
        $stored = $this->storedEntry(RosterEntryKind::AltGroup, $this->altGroupRow(['epoch' => 2000]));
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::AltGroup, $this->altGroupRow(['main' => 'Pan Bakewell', 'epoch' => 1000]));

        $this->commit($sync);

        $this->assertEquals($stored->payload, $stored->fresh()->payload);
        $this->assertSame($stored->fingerprint, $stored->fresh()->fingerprint);
        $this->assertSkipped($sync, RosterEntryKind::AltGroup, '12', SyncRowSkipReason::Older);
    }

    #[Test]
    public function it_passes_the_previous_payload_of_an_applied_alt_group(): void
    {
        $this->revision(1);
        $this->storedEntry(RosterEntryKind::AltGroup, $this->altGroupRow(['main' => 'Old Main', 'epoch' => 1000]));
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::AltGroup, $this->altGroupRow(['epoch' => 2000]));
        $this->stage($sync, RosterEntryKind::AltGroup, $this->altGroupRow(['id' => '13', 'main' => 'Liané Moonfall', 'members' => ['Player-5826-0A1B2C3F', 'Player-5826-0A1B2C90'], 'epoch' => 2000]));

        $this->commit($sync);

        $this->assertSame(
            [
                '12' => ['id' => '12', 'main' => 'Old Main', 'members' => ['Player-5826-0A1B2C3D', 'Player-5826-0A1B2C3E']],
                '13' => null,
            ],
            $this->committingChanges()->previous_alt_groups,
        );
    }

    #[Test]
    public function it_applies_a_ban_with_an_equal_epoch(): void
    {
        $this->revision(1);
        $stored = $this->banRow();
        $this->storedEntry(RosterEntryKind::Ban, $stored);
        $ban = $this->ban($stored);
        $sync = $this->openSync();
        $changed = $this->banRow(['bannedBy' => 'Pan Bakewell']);
        $this->stage($sync, RosterEntryKind::Ban, $changed);

        $this->commit($sync);

        $this->assertSame('Pan Bakewell', $ban->fresh()->banned_by);
        $this->assertSame($this->fingerprint(RosterEntryKind::Ban, $changed), $this->entry(RosterEntryKind::Ban, $changed['guid'])->fingerprint);
    }

    #[Test]
    public function it_skips_an_older_unban(): void
    {
        Log::spy();
        $this->revision(1);
        $stored = $this->banRow(['epoch' => 2000]);
        $this->storedEntry(RosterEntryKind::Ban, $stored);
        $ban = $this->ban($stored);
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Ban, $this->banRow(['unbanned' => true, 'reason' => null, 'epoch' => 1000]));

        $this->commit($sync);

        $this->assertNull($ban->fresh()->unbanned_at);
        $this->assertSkipped($sync, RosterEntryKind::Ban, $stored['guid'], SyncRowSkipReason::Older);
    }

    #[Test]
    public function it_records_no_changes_when_every_row_is_skipped(): void
    {
        $this->revision(1);
        $this->storedEntry(RosterEntryKind::Member, $this->memberRow(['epoch' => 2000]));
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Member, $this->memberRow(['level' => 59, 'epoch' => 1000]));

        $result = $this->commit($sync);

        $this->assertSame(1, $result->revision);
        $this->assertSame(UploadOutcome::NoChanges, $result->sync->outcome);
        $this->assertSame(1, RosterRevision::count());
        Event::assertDispatchedTimes(SyncCommitted::class, 1);
        Event::assertNotDispatched(SyncCommitting::class);
    }

    #[Test]
    public function it_skips_a_stale_reban_of_a_lifted_ban_without_a_roster_entry(): void
    {
        Log::spy();
        $this->revision(1);
        $row = $this->banRow();
        $ban = $this->ban($row, [
            'banned_at' => Carbon::createFromTimestamp($row['epoch'], config('app.timezone')),
            'unbanned_at' => Carbon::createFromTimestamp($row['epoch'] + 3600, config('app.timezone')),
        ]);
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Ban, $row);

        $this->commit($sync);

        $this->assertSame($row['epoch'] + 3600, $ban->fresh()->unbanned_at?->getTimestamp());
        $this->assertNull($this->entry(RosterEntryKind::Ban, $row['guid']));
        $this->assertSkipped($sync, RosterEntryKind::Ban, $row['guid'], SyncRowSkipReason::Older);
    }

    // ==================== future rows ====================

    #[Test]
    public function it_rejects_a_future_dated_row_and_applies_the_rest(): void
    {
        Log::spy();
        $this->revision(1);
        $stored = $this->storedEntry(RosterEntryKind::Member, $this->memberRow(['level' => 58, 'epoch' => 1000]));
        $sync = $this->openSync();
        $future = $this->memberRow(['level' => 60, 'epoch' => now()->getTimestamp() + self::CLOCK_TOLERANCE + 1]);
        $ordinary = $this->fixture('members')[1];
        $this->stage($sync, RosterEntryKind::Member, $future);
        $this->stage($sync, RosterEntryKind::Member, $ordinary);

        $this->commit($sync);

        $this->assertSame($stored->fingerprint, $stored->fresh()->fingerprint);
        $this->assertSame(1000, $stored->fresh()->epoch);
        $this->assertSkipped($sync, RosterEntryKind::Member, $future['guid'], SyncRowSkipReason::FutureTime);
        $this->assertSame($this->fingerprint(RosterEntryKind::Member, $ordinary), $this->entry(RosterEntryKind::Member, $ordinary['guid'])->fingerprint);
    }

    #[Test]
    public function it_accepts_a_row_dated_exactly_now(): void
    {
        $sync = $this->openSync();
        $row = $this->memberRow(['epoch' => now()->getTimestamp()]);
        $this->stage($sync, RosterEntryKind::Member, $row);

        $this->commit($sync, self::RANKS);

        $this->assertSame(now()->getTimestamp(), $this->entry(RosterEntryKind::Member, $row['guid'])->epoch);
    }

    #[Test]
    public function it_accepts_a_row_dated_within_the_clock_tolerance(): void
    {
        $sync = $this->openSync();
        $epoch = now()->getTimestamp() + self::CLOCK_TOLERANCE;
        $row = $this->memberRow(['epoch' => $epoch]);
        $this->stage($sync, RosterEntryKind::Member, $row);

        $this->commit($sync, self::RANKS);

        $this->assertSame($epoch, $this->entry(RosterEntryKind::Member, $row['guid'])->epoch);
    }

    #[Test]
    public function it_rejects_a_future_dated_row_with_nothing_stored(): void
    {
        Log::spy();
        $this->revision(1);
        $sync = $this->openSync();
        $future = $this->memberRow(['epoch' => now()->getTimestamp() + self::CLOCK_TOLERANCE + 1]);
        $this->stage($sync, RosterEntryKind::Member, $future);

        $result = $this->commit($sync);

        $this->assertNull($this->entry(RosterEntryKind::Member, $future['guid']));
        $this->assertSame(UploadOutcome::NoChanges, $result->sync->outcome);
        $this->assertSkipped($sync, RosterEntryKind::Member, $future['guid'], SyncRowSkipReason::FutureTime);
    }

    #[Test]
    public function it_prefers_no_op_over_future(): void
    {
        Log::spy();
        $this->revision(1);
        $stored = $this->storedEntry(RosterEntryKind::Member, $this->memberRow(['epoch' => 1000]));
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Member, $this->memberRow(['epoch' => now()->getTimestamp() + self::CLOCK_TOLERANCE + 1]));

        $result = $this->commit($sync);

        $this->assertSame(UploadOutcome::NoChanges, $result->sync->outcome);
        $this->assertSame(1000, $stored->fresh()->epoch);
        Log::shouldNotHaveReceived('info');
    }

    // ==================== removal evidence ====================

    #[Test]
    public function it_keeps_a_member_missing_from_both_lists(): void
    {
        Log::spy();
        $this->revision(1);
        $guid = $this->memberRow()['guid'];
        $this->storedEntry(RosterEntryKind::Member, $this->memberRow());
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Member, $this->fixture('members')[1]);

        $this->commit($sync, removed: new RosterRemovalsData(members: [$guid]));

        $this->assertNotNull($this->entry(RosterEntryKind::Member, $guid));
        $this->assertSkipped($sync, RosterEntryKind::Member, $guid, SyncRowSkipReason::NoEvidence);
        $this->assertSame([], $this->committingChanges()->departed_members);
    }

    #[Test]
    public function it_removes_a_member_whose_leaver_row_won(): void
    {
        $this->revision(1);
        $guid = $this->memberRow()['guid'];
        $this->storedEntry(RosterEntryKind::Member, $this->memberRow(['epoch' => 1000]));
        $sync = $this->openSync();
        $this->stage($sync, RosterEntryKind::Leaver, $this->leaverRow(['guid' => $guid, 'epoch' => 2000]));

        $this->commit($sync, removed: new RosterRemovalsData(members: [$guid]));

        $this->assertNull($this->entry(RosterEntryKind::Member, $guid));
        $this->assertNotNull($this->entry(RosterEntryKind::Leaver, $guid));
        $this->assertSame([$guid], $this->committingChanges()->departed_members);
    }

    #[Test]
    public function it_keeps_a_member_whose_leaver_entry_is_older(): void
    {
        Log::spy();
        $this->revision(1);
        $guid = $this->memberRow()['guid'];
        $this->storedEntry(RosterEntryKind::Leaver, $this->leaverRow(['guid' => $guid, 'epoch' => 1000]));
        $this->storedEntry(RosterEntryKind::Member, $this->memberRow(['epoch' => 2000]));
        $sync = $this->openSync();

        $this->commit($sync, removed: new RosterRemovalsData(members: [$guid]));

        $this->assertNotNull($this->entry(RosterEntryKind::Member, $guid));
        $this->assertSkipped($sync, RosterEntryKind::Member, $guid, SyncRowSkipReason::Older);
    }

    #[Test]
    public function it_keeps_an_alt_group_missing_from_the_file(): void
    {
        Log::spy();
        $this->revision(1);
        $this->storedEntry(RosterEntryKind::AltGroup, $this->altGroupRow());
        $sync = $this->openSync();

        $this->commit($sync, removed: new RosterRemovalsData(alt_groups: ['12']));

        $this->assertNotNull($this->entry(RosterEntryKind::AltGroup, '12'));
        $this->assertSkipped($sync, RosterEntryKind::AltGroup, '12', SyncRowSkipReason::NoEvidence);
    }

    #[Test]
    public function it_never_unbans_a_ban_missing_from_the_file(): void
    {
        $this->revision(1);
        $row = $this->banRow();
        $this->storedEntry(RosterEntryKind::Ban, $row);
        $ban = $this->ban($row);
        $sync = $this->openSync();

        $this->commit($sync, removed: new RosterRemovalsData(bans: [$row['guid']]));

        $this->assertNull($this->entry(RosterEntryKind::Ban, $row['guid']));
        $this->assertNull($ban->fresh()->unbanned_at);
        $this->assertSame([], $this->committingChanges()->ban_ids);
    }

    // ==================== ban permissions ====================

    #[Test]
    #[Group('authorization')]
    public function it_skips_a_staged_ban_the_user_may_no_longer_create(): void
    {
        Log::spy();
        $sync = $this->openSync();
        $row = $this->banRow();
        $this->stage($sync, RosterEntryKind::Ban, $row);
        $this->stage($sync, RosterEntryKind::Member, $this->memberRow());
        $this->uploader->discordRoles->first()->revokePermissionTo('create-grm-bans');

        $this->commit($sync, self::RANKS);

        $this->assertSame(0, Ban::count());
        $this->assertNull($this->entry(RosterEntryKind::Ban, $row['guid']));
        $this->assertSkipped($sync, RosterEntryKind::Ban, $row['guid'], SyncRowSkipReason::Forbidden);
        $this->assertSame([], $this->committingChanges()->ban_ids);
    }

    #[Test]
    #[Group('authorization')]
    public function it_skips_a_new_ban_another_officer_created_first_for_a_create_only_user(): void
    {
        Log::spy();
        $this->revision(1);
        $createOnly = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'create-grm-bans']);
        $stored = $this->storedEntry(RosterEntryKind::Ban, $this->banRow(['bannedBy' => 'Pan Bakewell', 'epoch' => 1000]));
        $sync = $this->openSync($createOnly);
        $this->stage($sync, RosterEntryKind::Ban, $this->banRow(['epoch' => 2000]));

        $this->commit($sync);

        $this->assertSame($stored->fingerprint, $stored->fresh()->fingerprint);
        $this->assertSame(1000, $stored->fresh()->epoch);
        $this->assertSkipped($sync, RosterEntryKind::Ban, $stored->key, SyncRowSkipReason::Forbidden);
    }

    #[Test]
    #[Group('authorization')]
    public function it_skips_ban_removals_without_update_grm_bans(): void
    {
        Log::spy();
        $this->revision(1);
        $createOnly = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'create-grm-bans']);
        $stored = $this->storedEntry(RosterEntryKind::Ban, $this->banRow());
        $sync = $this->openSync($createOnly);

        $this->commit($sync, removed: new RosterRemovalsData(bans: [$stored->key]));

        $this->assertNotNull($stored->fresh());
        $this->assertSkipped($sync, RosterEntryKind::Ban, $stored->key, SyncRowSkipReason::Forbidden);
    }

    #[Test]
    #[Group('authorization')]
    public function it_applies_the_rest_of_the_sync_when_ban_rows_are_forbidden(): void
    {
        $sync = $this->openSync($this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters']));
        $this->stageFixture($sync);

        $this->commit($sync, self::RANKS);

        $this->assertSame(3, RosterEntry::where('kind', RosterEntryKind::Member)->count());
        $this->assertNull($this->entry(RosterEntryKind::Ban, $this->banRow()['guid']));
        $this->assertSame(0, Ban::count());
    }

    #[Test]
    #[Group('authorization')]
    public function it_treats_a_logged_ban_without_a_roster_entry_as_existing_for_permissions(): void
    {
        Log::spy();
        $this->revision(1);
        $createOnly = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'create-grm-bans']);
        $row = $this->banRow();
        $ban = $this->ban($row, ['banned_at' => Carbon::createFromTimestamp($row['epoch'], config('app.timezone'))]);
        $sync = $this->openSync($createOnly);
        $this->stage($sync, RosterEntryKind::Ban, $this->banRow(['unbanned' => true, 'reason' => null, 'epoch' => $row['epoch'] + 3600]));

        $this->commit($sync);

        $this->assertNull($ban->fresh()->unbanned_at);
        $this->assertNull($this->entry(RosterEntryKind::Ban, $row['guid']));
        $this->assertSkipped($sync, RosterEntryKind::Ban, $row['guid'], SyncRowSkipReason::Forbidden);
    }

    #[Test]
    #[Group('authorization')]
    public function it_prefers_forbidden_over_no_op(): void
    {
        Log::spy();
        $this->revision(1);
        $createOnly = $this->userWithPermissions(['use-desktop-client', 'upload-grm-rosters', 'create-grm-bans']);
        $row = $this->banRow();
        $this->storedEntry(RosterEntryKind::Ban, $row);
        $sync = $this->openSync($createOnly);
        $this->stage($sync, RosterEntryKind::Ban, $row);

        $this->commit($sync);

        $this->assertSkipped($sync, RosterEntryKind::Ban, $row['guid'], SyncRowSkipReason::Forbidden);
    }

    // ==================== helpers ====================

    /**
     * @param  array<int, string>|null  $ranks
     */
    private function commit(Upload $sync, ?array $ranks = null, ?RosterRemovalsData $removed = null): CommitSyncResultData
    {
        return CommitSync::run($sync, $ranks, $removed ?? new RosterRemovalsData);
    }

    private function openSync(?User $user = null): Upload
    {
        return Upload::factory()->staging($user === null ? $this->desktopClient : null)->create([
            'user_id' => ($user ?? $this->uploader)->id,
            'game_version_id' => $this->forever->id,
            'base_revision' => (int) RosterRevision::where('game_version_id', $this->forever->id)->max('revision'),
        ]);
    }

    /**
     * @param  array<int, string>  $ranks
     */
    private function revision(int $revision, array $ranks = self::RANKS): RosterRevision
    {
        return RosterRevision::factory()->create([
            'game_version_id' => $this->forever->id,
            'revision' => $revision,
            'ranks' => $ranks,
            'ranks_fingerprint' => ComputeFingerprint::run(new RanksData($ranks)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function stage(Upload $sync, RosterEntryKind $kind, array $row): SyncRow
    {
        return SyncRow::factory()->create([
            'upload_id' => $sync->id,
            'kind' => $kind,
            'key' => (string) $row[$kind->keyAttribute()],
            'payload' => $kind->dataClass()::from($row),
            'epoch' => $row['epoch'],
        ]);
    }

    private function stageFixture(Upload $sync): void
    {
        foreach ($this->fixtureRows() as [$kind, $row]) {
            $this->stage($sync, $kind, $row);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function storedEntry(RosterEntryKind $kind, array $row, int $revision = 1): RosterEntry
    {
        return RosterEntry::factory()->create([
            'game_version_id' => $this->forever->id,
            'kind' => $kind,
            'key' => (string) $row[$kind->keyAttribute()],
            'payload' => $kind->dataClass()::from($kind === RosterEntryKind::Ban ? [...$row, 'reason' => null] : $row),
            'fingerprint' => $this->fingerprint($kind, $row),
            'epoch' => $row['epoch'],
            'revision' => $revision,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $attributes
     */
    private function ban(array $row, array $attributes = []): Ban
    {
        return Ban::factory()->create([
            'game_version_id' => $this->forever->id,
            'grm_guid' => $row['guid'],
            'name' => $row['name'],
            'banned_by' => $row['bannedBy'],
            'reason' => $row['reason'],
            ...$attributes,
        ]);
    }

    private function entry(RosterEntryKind $kind, string $key): ?RosterEntry
    {
        return RosterEntry::where('game_version_id', $this->forever->id)->where('kind', $kind)->where('key', $key)->first();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function fingerprint(RosterEntryKind $kind, array $row): string
    {
        return ComputeFingerprint::run($kind->dataClass()::from($row));
    }

    private function assertSkipped(Upload $sync, RosterEntryKind $kind, string $key, SyncRowSkipReason $reason): void
    {
        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context = []): bool => $message === 'GRM sync skipped rows.'
                && $context['sync'] === $sync->id
                && in_array(['kind' => $kind->value, 'key' => $key, 'reason' => $reason->value], $context['skipped'], true))
            ->once();
    }

    private function committingChanges(): SyncChangesData
    {
        return Event::dispatched(SyncCommitting::class)->sole()[0]->changes;
    }

    /**
     * @return list<array{0: RosterEntryKind, 1: array<string, mixed>}>
     */
    private function fixtureRows(): array
    {
        $rows = [];

        foreach (RosterEntryKind::cases() as $kind) {
            foreach ($this->fixture($kind->fingerprintGroup()) as $row) {
                $rows[] = [$kind, $row];
            }
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fixture(string $group): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/GuildRosterManager/forever-roster.json')), true)[$group];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function memberRow(array $overrides = []): array
    {
        return [...$this->fixture('members')[0], ...$overrides];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function leaverRow(array $overrides = []): array
    {
        return [...$this->fixture('leavers')[0], ...$overrides];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function altGroupRow(array $overrides = []): array
    {
        return [...$this->fixture('altGroups')[0], ...$overrides];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function banRow(array $overrides = []): array
    {
        return [...$this->fixture('bans')[0], ...$overrides];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        $role = DiscordRole::factory()->grantsPermissions()->create();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $role->givePermissionTo($permission);
        }

        $user = User::factory()->create();
        $user->discordRoles()->attach($role->id);
        $user->load('discordRoles.permissions');

        return $user;
    }
}
