<?php

namespace App\Actions\GuildRosterManager;

use App\Casts\AsRosterEntryData;
use App\Contracts\Data\GuildRosterManager\RosterEntryData;
use App\Data\GuildRosterManager\BanData;
use App\Data\GuildRosterManager\CommitSyncResultData;
use App\Data\GuildRosterManager\RanksData;
use App\Data\GuildRosterManager\RosterRemovalsData;
use App\Data\GuildRosterManager\SyncChangesData;
use App\Enums\GuildRosterManager\CommitSyncStatus;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Enums\GuildRosterManager\RowVerdict;
use App\Enums\GuildRosterManager\SyncRowSkipReason;
use App\Enums\GuildRosterManager\UploadOutcome;
use App\Events\DesktopClient\SyncCommitted;
use App\Events\DesktopClient\SyncCommitting;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRosterManager\Ban;
use App\Models\GuildRosterManager\RosterEntry;
use App\Models\GuildRosterManager\RosterRevision;
use App\Models\GuildRosterManager\SyncRow;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Commits a staged desktop sync to its game version's roster state in one locked transaction.
 *
 * A repeated commit replays the stored result. An expired sync, a sync whose open ticket has lapsed
 * and a sync whose base revision is no longer current are refused. Each staged row that differs from
 * the stored entry is applied, removals are applied, the ban log is updated, the staged rows are
 * deleted, and `SyncCommitting` (with changes) or `SyncCommitted` (without) is dispatched once the
 * transaction commits.
 */
class CommitSync
{
    use AsAction;

    private const int CHUNK_SIZE = 500;

    /**
     * @param  array<int, string>|null  $ranks
     *
     * @throws ValidationException
     */
    public function handle(Upload $sync, ?array $ranks, RosterRemovalsData $removed): CommitSyncResultData
    {
        return DB::transaction(function () use ($sync, $ranks, $removed): CommitSyncResultData {
            $gameVersion = GameVersion::whereKey($sync->game_version_id)->lockForUpdate()->firstOrFail();
            $sync = Upload::whereKey($sync->id)->lockForUpdate()->firstOrFail();
            $previous = $gameVersion->currentRosterRevision()->first();
            $currentRevision = $previous?->revision ?? 0;

            $refusal = $this->refuse($sync, $currentRevision);

            if ($refusal !== null) {
                return $refusal;
            }

            $this->validate($sync, $ranks, $previous, $removed);

            $nextRevision = $currentRevision + 1;
            $banAccess = [
                'create' => $sync->user->can('create', Ban::class),
                'update' => $sync->user->can('update', Ban::class),
            ];
            $classified = $this->classify($sync, $banAccess);
            $applied = array_values(array_filter($classified, fn (array $item): bool => $item['verdict'] === RowVerdict::Apply));
            $previousAltGroups = $this->writeEntries($gameVersion, $applied, $nextRevision);
            $removals = $this->applyRemovals($gameVersion, $removed, $classified, $banAccess);
            $banIds = $this->recordBans($gameVersion, $applied);
            ['removed' => $removedCount, 'departed' => $departed, 'skipped' => $skippedRemovals] = $removals;

            $ranksFingerprint = $ranks === null ? $previous->ranks_fingerprint : ComputeFingerprint::run(new RanksData($ranks));
            $hasChanges = $applied !== [] || $removedCount > 0 || $ranksFingerprint !== $previous?->ranks_fingerprint;

            if ($hasChanges) {
                RosterRevision::create([
                    'game_version_id' => $gameVersion->id,
                    'revision' => $nextRevision,
                    'upload_id' => $sync->id,
                    'committed_at' => now(),
                    'ranks' => $ranks ?? $previous->ranks,
                    'ranks_fingerprint' => $ranksFingerprint,
                ]);
            }

            $sync->markOutcome($hasChanges ? UploadOutcome::Received : UploadOutcome::NoChanges, [
                'revision' => $hasChanges ? $nextRevision : $currentRevision,
                'member_count' => RosterEntry::where('game_version_id', $gameVersion->id)->where('kind', RosterEntryKind::Member)->count(),
                'expires_at' => null,
                'ticket_expires_at' => null,
            ]);
            $sync->device()->update(['last_upload_at' => now()]);
            $sync->syncRows()->delete();
            $this->logSkipped($sync, $classified, $skippedRemovals);

            if ($hasChanges) {
                SyncCommitting::dispatch($sync, new SyncChangesData(
                    members: $this->appliedKeys($applied, RosterEntryKind::Member),
                    leavers: $this->appliedKeys($applied, RosterEntryKind::Leaver),
                    alt_groups: $this->appliedKeys($applied, RosterEntryKind::AltGroup),
                    departed_members: $departed,
                    previous_alt_groups: $previousAltGroups,
                    ban_ids: $banIds,
                ));
            } else {
                SyncCommitted::dispatch($sync);
            }

            return new CommitSyncResultData(CommitSyncStatus::Committed, $sync, $sync->revision);
        });
    }

    /**
     * Replay an already committed sync, or refuse a sync that can no longer be committed.
     */
    private function refuse(Upload $sync, int $currentRevision): ?CommitSyncResultData
    {
        $replay = match ($sync->outcome) {
            UploadOutcome::Received,
            UploadOutcome::Processing,
            UploadOutcome::Processed,
            UploadOutcome::NoChanges,
            UploadOutcome::Failed => CommitSyncStatus::Committed,
            UploadOutcome::Superseded => CommitSyncStatus::RevisionConflict,
            UploadOutcome::Abandoned => CommitSyncStatus::Abandoned,
            UploadOutcome::Expired => CommitSyncStatus::Expired,
            UploadOutcome::Staging => null,
        };

        if ($replay !== null) {
            return new CommitSyncResultData($replay, $sync, $replay === CommitSyncStatus::Committed ? ($sync->revision ?? 0) : $currentRevision);
        }

        if ($sync->is_expired) {
            $sync->markOutcome(UploadOutcome::Expired);

            return new CommitSyncResultData(CommitSyncStatus::Expired, $sync, $currentRevision);
        }

        if ($sync->is_ticket_expired) {
            $sync->markOutcome(UploadOutcome::Expired, ['expires_at' => null, 'ticket_expires_at' => null]);

            return new CommitSyncResultData(CommitSyncStatus::TicketExpired, $sync, $currentRevision);
        }

        if ($sync->base_revision !== $currentRevision) {
            $sync->markOutcome(UploadOutcome::Superseded);

            return new CommitSyncResultData(CommitSyncStatus::RevisionConflict, $sync, $currentRevision);
        }

        return null;
    }

    /**
     * @param  array<int, string>|null  $ranks
     *
     * @throws ValidationException
     */
    private function validate(Upload $sync, ?array $ranks, ?RosterRevision $previous, RosterRemovalsData $removed): void
    {
        if ($ranks === null && $previous === null) {
            throw ValidationException::withMessages(['ranks' => 'The ranks are required for the first sync.']);
        }

        $errors = [];

        foreach (RosterEntryKind::cases() as $kind) {
            $keys = $removed->keysFor($kind);

            if ($keys === []) {
                continue;
            }

            $staged = SyncRow::where('upload_id', $sync->id)->where('kind', $kind)->whereIn('key', $keys)->pluck('key')->flip();

            foreach ($keys as $index => $key) {
                if ($staged->has($key)) {
                    $errors["removed.{$kind->fingerprintGroup()}.{$index}"] = 'The key is both staged and removed.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Give every staged row a verdict against the stored entries, without writing state.
     *
     * @param  array{create: bool, update: bool}  $banAccess
     * @return list<array{kind: RosterEntryKind, row: SyncRow, data: RosterEntryData, fingerprint: string, stored: RosterEntry|null, verdict: RowVerdict}>
     */
    private function classify(Upload $sync, array $banAccess): array
    {
        $computeFingerprint = ComputeFingerprint::make();
        $latestAllowedEpoch = now()->getTimestamp() + (int) config('services.grm.clock_tolerance_seconds');
        $classified = [];

        foreach (RosterEntryKind::cases() as $kind) {
            SyncRow::where('upload_id', $sync->id)
                ->where('kind', $kind)
                ->chunkById(self::CHUNK_SIZE, function (Collection $rows) use ($sync, $kind, $banAccess, $computeFingerprint, $latestAllowedEpoch, &$classified): void {
                    $entries = $this->entries($sync->game_version_id, $this->comparedKinds($kind), $rows->pluck('key')->all())->groupBy('key');
                    $loggedBans = $kind === RosterEntryKind::Ban
                        ? Ban::where('game_version_id', $sync->game_version_id)->whereIn('grm_guid', $rows->pluck('key'))->get()->keyBy('grm_guid')
                        : collect();
                    $existingBans = $kind === RosterEntryKind::Ban
                        ? ListExistingBanGuids::run($sync->game_version_id, $rows->pluck('key'))->flip()
                        : collect();

                    foreach ($rows as $row) {
                        $data = $row->payload;
                        $fingerprint = $computeFingerprint->handle($data);
                        $stored = $entries->get($row->key, collect());
                        $sameKind = $stored->first(fn (RosterEntry $entry): bool => $entry->kind === $kind);

                        $classified[] = [
                            'kind' => $kind,
                            'row' => $row,
                            'data' => $data,
                            'fingerprint' => $fingerprint,
                            'stored' => $sameKind,
                            'verdict' => $this->verdict($kind, $row, $fingerprint, $stored, $sameKind, $loggedBans->get($row->key), $existingBans->has($row->key), $banAccess, $latestAllowedEpoch),
                        ];
                    }
                });
        }

        return $classified;
    }

    /**
     * Decide what to do with a staged row: forbidden ban rows, unchanged rows and future-dated rows first,
     * then the newer (or equal) raw epoch wins against the stored entries it is compared with. A ban with
     * a ban log row but no stored entry is compared with the later of the row's ban and unban times.
     *
     * @param  Collection<int, RosterEntry>  $stored  The stored entries the row is compared with.
     * @param  Ban|null  $loggedBan  The ban log row for a ban row's GUID.
     * @param  bool  $banExists  Whether the ban row's GUID already has a ban, as listed by `ListExistingBanGuids`.
     * @param  array{create: bool, update: bool}  $banAccess
     * @param  int  $latestAllowedEpoch  The current time plus the GRM clock tolerance; a later row epoch is future-dated.
     */
    private function verdict(
        RosterEntryKind $kind,
        SyncRow $row,
        string $fingerprint,
        Collection $stored,
        ?RosterEntry $sameKind,
        ?Ban $loggedBan,
        bool $banExists,
        array $banAccess,
        int $latestAllowedEpoch,
    ): RowVerdict {
        if ($kind === RosterEntryKind::Ban && ! ($banExists ? $banAccess['update'] : $banAccess['create'])) {
            return RowVerdict::Forbidden;
        }

        if ($sameKind?->fingerprint === $fingerprint) {
            return RowVerdict::NoOp;
        }

        if ($row->epoch > $latestAllowedEpoch) {
            return RowVerdict::Future;
        }

        $storedEpoch = match (true) {
            $stored->isNotEmpty() => $stored->max('epoch'),
            $loggedBan !== null => max($loggedBan->banned_at?->getTimestamp() ?? 0, $loggedBan->unbanned_at?->getTimestamp() ?? 0),
            default => null,
        };

        if ($storedEpoch === null) {
            return RowVerdict::Apply;
        }

        return $row->epoch >= $storedEpoch ? RowVerdict::Apply : RowVerdict::Older;
    }

    /**
     * Upsert the applied rows into the roster state, ban rows without their reason.
     *
     * @param  list<array{kind: RosterEntryKind, row: SyncRow, data: RosterEntryData, fingerprint: string, stored: RosterEntry|null, verdict: RowVerdict}>  $applied
     * @return array<string, array<string, mixed>|null> Each applied alt group's stored payload before the upsert, or null for a new group.
     */
    private function writeEntries(GameVersion $gameVersion, array $applied, int $revision): array
    {
        $previousAltGroups = [];

        foreach ($applied as $item) {
            if ($item['kind'] === RosterEntryKind::AltGroup) {
                $storedPayload = $item['stored']?->getRawOriginal('payload');

                $previousAltGroups[$item['row']->key] = $storedPayload === null
                    ? null
                    : json_decode($storedPayload, true, flags: JSON_THROW_ON_ERROR);
            }
        }

        foreach (array_chunk($applied, self::CHUNK_SIZE) as $chunk) {
            RosterEntry::upsert(
                array_map(fn (array $item): array => [
                    'game_version_id' => $gameVersion->id,
                    'kind' => $item['kind']->value,
                    'key' => $item['row']->key,
                    'payload' => AsRosterEntryData::serialize($item['data'], $item['kind'] === RosterEntryKind::Ban ? ['reason'] : []),
                    'fingerprint' => $item['fingerprint'],
                    'epoch' => $item['row']->epoch,
                    'revision' => $revision,
                ], $chunk),
                ['game_version_id', 'kind', 'key'],
                ['payload', 'fingerprint', 'epoch', 'revision', 'updated_at'],
            );
        }

        return $previousAltGroups;
    }

    /**
     * Delete the removed keys the removal rules allow, ignoring keys missing from the state.
     *
     * A member is removed only when its GUID has a leaver entry not older than its member entry, and is kept
     * when the same sync's leaver row for it was older or future-dated. A leaver is dropped, except the leaver
     * entry of a member removed by the same commit, which is kept. An alt group is kept. A ban is dropped from
     * the state only when the sync's user may update bans.
     *
     * @param  list<array{kind: RosterEntryKind, row: SyncRow, data: RosterEntryData, fingerprint: string, stored: RosterEntry|null, verdict: RowVerdict}>  $classified
     * @param  array{create: bool, update: bool}  $banAccess
     * @return array{removed: int, departed: list<string>, skipped: list<array{kind: string, key: string, reason: string}>}
     */
    private function applyRemovals(GameVersion $gameVersion, RosterRemovalsData $removed, array $classified, array $banAccess): array
    {
        $removedCount = 0;
        $departed = [];
        $skipped = [];
        $leaverVerdicts = collect($classified)
            ->filter(fn (array $item): bool => $item['kind'] === RosterEntryKind::Leaver)
            ->mapWithKeys(fn (array $item): array => [$item['row']->key => $item['verdict']]);

        foreach (RosterEntryKind::cases() as $kind) {
            $keys = $removed->keysFor($kind);

            if ($keys === []) {
                continue;
            }

            $stored = $this->entries($gameVersion->id, $this->comparedKinds($kind), $keys)->groupBy('key');
            $departedGuids = array_flip($departed);
            $deletable = [];

            foreach ($keys as $key) {
                $entries = $stored->get($key, collect());
                $entry = $entries->first(fn (RosterEntry $entry): bool => $entry->kind === $kind);

                if ($entry === null || ($kind === RosterEntryKind::Leaver && isset($departedGuids[$key]))) {
                    continue;
                }

                $reason = match ($kind) {
                    RosterEntryKind::Member => $this->memberRemovalSkip($entry, $entries, $leaverVerdicts->get($key)),
                    RosterEntryKind::Leaver => null,
                    RosterEntryKind::AltGroup => SyncRowSkipReason::NoEvidence,
                    RosterEntryKind::Ban => $banAccess['update'] ? null : SyncRowSkipReason::Forbidden,
                };

                if ($reason !== null) {
                    $skipped[] = ['kind' => $kind->value, 'key' => $key, 'reason' => $reason->value];

                    continue;
                }

                $deletable[] = $key;
            }

            foreach (array_chunk($deletable, self::CHUNK_SIZE) as $chunk) {
                RosterEntry::where('game_version_id', $gameVersion->id)->where('kind', $kind)->whereIn('key', $chunk)->delete();
            }

            $removedCount += count($deletable);

            if ($kind === RosterEntryKind::Member) {
                $departed = $deletable;
            }
        }

        return ['removed' => $removedCount, 'departed' => $departed, 'skipped' => $skipped];
    }

    /**
     * Get why a member's removal is skipped, or null when its GUID has a winning leaver entry.
     *
     * @param  Collection<int, RosterEntry>  $entries  The GUID's stored member and leaver entries.
     */
    private function memberRemovalSkip(RosterEntry $member, Collection $entries, ?RowVerdict $leaverVerdict): ?SyncRowSkipReason
    {
        if ($leaverVerdict === RowVerdict::Older || $leaverVerdict === RowVerdict::Future) {
            return SyncRowSkipReason::Older;
        }

        $leaver = $entries->first(fn (RosterEntry $entry): bool => $entry->kind === RosterEntryKind::Leaver);

        if ($leaver === null) {
            return SyncRowSkipReason::NoEvidence;
        }

        return $leaver->epoch >= $member->epoch ? null : SyncRowSkipReason::Older;
    }

    /**
     * Log the staged rows and removals the commit skipped, by kind, key and reason.
     *
     * @param  list<array{kind: RosterEntryKind, row: SyncRow, data: RosterEntryData, fingerprint: string, stored: RosterEntry|null, verdict: RowVerdict}>  $classified
     * @param  list<array{kind: string, key: string, reason: string}>  $skippedRemovals
     */
    private function logSkipped(Upload $sync, array $classified, array $skippedRemovals): void
    {
        $skippedRows = collect($classified)
            ->filter(fn (array $item): bool => $item['verdict']->skipReason() !== null)
            ->map(fn (array $item): array => ['kind' => $item['kind']->value, 'key' => $item['row']->key, 'reason' => $item['verdict']->skipReason()->value])
            ->values()
            ->all();

        $skipped = [...$skippedRows, ...$skippedRemovals];

        if ($skipped === []) {
            return;
        }

        Log::info('GRM sync skipped rows.', ['sync' => $sync->id, 'skipped' => $skipped]);
    }

    /**
     * Record the applied ban rows in the ban log.
     *
     * @param  list<array{kind: RosterEntryKind, row: SyncRow, data: RosterEntryData, fingerprint: string, stored: RosterEntry|null, verdict: RowVerdict}>  $applied
     * @return list<string> The UUIDs of the bans created or changed.
     */
    private function recordBans(GameVersion $gameVersion, array $applied): array
    {
        $rows = collect($applied)
            ->filter(fn (array $item): bool => $item['kind'] === RosterEntryKind::Ban)
            ->map(fn (array $item): BanData => $item['data'])
            ->values();

        [$unbans, $bans] = $rows->partition(fn (BanData $ban): bool => $ban->unbanned);

        return [
            ...$this->recordBanned($gameVersion, $bans),
            ...$this->recordUnbanned($gameVersion, $unbans),
        ];
    }

    /**
     * @param  Collection<int, BanData>  $rows
     * @return list<string>
     */
    private function recordBanned(GameVersion $gameVersion, Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $existing = Ban::where('game_version_id', $gameVersion->id)->whereIn('grm_guid', $rows->pluck('guid'))->get()->keyBy('grm_guid');
        $unlinked = $rows->pluck('guid')->filter(fn (string $guid): bool => $existing->get($guid)?->character_id === null)->flip();
        $candidates = $unlinked->isEmpty() ? collect() : Character::where('game_version_id', $gameVersion->id)->get();
        $resolveBanCharacter = ResolveBanCharacter::make();
        $banIds = [];

        foreach ($rows as $row) {
            $attributes = [
                'name' => $row->name,
                'banned_by' => $row->banned_by,
                'banned_at' => $row->epoch === 0 ? null : Carbon::createFromTimestamp($row->epoch, config('app.timezone')),
                'reason' => $row->reason,
                'unbanned_at' => null,
            ];

            if ($unlinked->has($row->guid)) {
                $character = $resolveBanCharacter->handle($gameVersion, $row->name, $candidates);

                if ($character !== null) {
                    $attributes['character_id'] = $character->id;
                    $attributes['user_id'] = $character->user_id;
                }
            }

            $ban = $existing->get($row->guid) ?? new Ban(['game_version_id' => $gameVersion->id, 'grm_guid' => $row->guid]);
            $ban->fill($attributes)->save();

            if ($ban->wasRecentlyCreated || $ban->wasChanged()) {
                $banIds[] = $ban->id;
            }
        }

        return $banIds;
    }

    /**
     * Mark the active bans of the unban rows as unbanned at each row's epoch, or now when the epoch is 0.
     *
     * @param  Collection<int, BanData>  $rows
     * @return list<string>
     */
    private function recordUnbanned(GameVersion $gameVersion, Collection $rows): array
    {
        $banIds = [];

        foreach ($rows->chunk(self::CHUNK_SIZE) as $chunk) {
            $activeIds = Ban::where('game_version_id', $gameVersion->id)
                ->whereIn('grm_guid', $chunk->pluck('guid'))
                ->active()
                ->pluck('id', 'grm_guid');

            $idsByUnbannedAt = $chunk
                ->filter(fn (BanData $row): bool => $activeIds->has($row->guid))
                ->groupBy(fn (BanData $row): int => $row->epoch === 0 ? now()->getTimestamp() : $row->epoch)
                ->map(fn (Collection $group): array => $group->map(fn (BanData $row): string => $activeIds->get($row->guid))->all());

            foreach ($idsByUnbannedAt as $timestamp => $ids) {
                Ban::whereKey($ids)->update(['unbanned_at' => Carbon::createFromTimestamp($timestamp, config('app.timezone'))]);
                $banIds = [...$banIds, ...$ids];
            }
        }

        return $banIds;
    }

    /**
     * Load the game version's stored entries of the given kinds for the given keys.
     *
     * @param  list<RosterEntryKind>  $kinds
     * @param  array<int, string>  $keys
     * @return Collection<int, RosterEntry>
     */
    private function entries(int $gameVersionId, array $kinds, array $keys): Collection
    {
        return collect($keys)
            ->chunk(self::CHUNK_SIZE)
            ->flatMap(fn (Collection $chunk): Collection => RosterEntry::where('game_version_id', $gameVersionId)
                ->whereIn('kind', $kinds)
                ->whereIn('key', $chunk->values())
                ->get());
    }

    /**
     * Get the kinds a row of the given kind is compared with: members and leavers share a GUID.
     *
     * @return list<RosterEntryKind>
     */
    private function comparedKinds(RosterEntryKind $kind): array
    {
        return match ($kind) {
            RosterEntryKind::Member, RosterEntryKind::Leaver => [RosterEntryKind::Member, RosterEntryKind::Leaver],
            default => [$kind],
        };
    }

    /**
     * @param  list<array{kind: RosterEntryKind, row: SyncRow, data: RosterEntryData, fingerprint: string, stored: RosterEntry|null, verdict: RowVerdict}>  $applied
     * @return list<string>
     */
    private function appliedKeys(array $applied, RosterEntryKind $kind): array
    {
        return collect($applied)
            ->filter(fn (array $item): bool => $item['kind'] === $kind)
            ->map(fn (array $item): string => $item['row']->key)
            ->values()
            ->all();
    }
}
