<?php

namespace App\Jobs\GuildRosterManager;

use App\Actions\GuildRosterManager\LinkAltGroups;
use App\Actions\GuildRosterManager\SyncLeaver;
use App\Actions\GuildRosterManager\SyncMember;
use App\Data\GuildRosterManager\AltGroupData;
use App\Data\GuildRosterManager\LeaverData;
use App\Data\GuildRosterManager\MemberData;
use App\Data\GuildRosterManager\RowResultData;
use App\Data\GuildRosterManager\SyncChangesData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use App\Enums\GuildRosterManager\UploadOutcome;
use App\Events\DesktopClient\SyncCommitted;
use App\Models\GuildRank;
use App\Models\GuildRosterManager\RosterEntry;
use App\Models\GuildRosterManager\RosterRevision;
use App\Models\GuildRosterManager\Upload;
use App\Models\GuildRosterManager\UploadRow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\MaxExceptions;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies one committed desktop sync to the game version's characters, after every earlier revision's
 * job has finished. It processes the member, leaver and alt group keys the commit changed, the members
 * that left, and the GUIDs whose last outcome was a failure or warning, reading each row from the
 * roster state. A row that throws is reported and recorded as failed, and the other rows still run.
 * The alt groups of members created in the job or processed from a retry row are linked again. It
 * stores each row's outcome, marking it as the GUID's latest, and the sync's counts, then fires
 * `SyncCommitted`.
 */
#[Tries(0)]
#[MaxExceptions(3)]
#[Backoff(60)]
#[Timeout(900)]
class SyncRoster implements ShouldQueue
{
    use Queueable;

    /**
     * The game version the sync belongs to.
     */
    public readonly int $gameVersionId;

    /**
     * @param  string  $uploadId  The committed sync's `grm_uploads` UUID.
     */
    public function __construct(
        public readonly string $uploadId,
        public readonly SyncChangesData $changes,
    ) {
        $this->gameVersionId = Upload::findOrFail($uploadId)->game_version_id;
    }

    /**
     * Get the time after which the job is no longer attempted.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }

    /**
     * Get the middleware the job passes through: one sync job per game version at a time.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("grm-sync:{$this->gameVersionId}"))->releaseAfter(60)->expireAfter(960)];
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['grm-sync', "game-version:{$this->gameVersionId}"];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $sync = Upload::with('gameVersion')->findOrFail($this->uploadId);

        if (in_array($sync->outcome, $this->finishedOutcomes(), true)) {
            return;
        }

        if ($this->hasEarlierUnfinishedRevision($sync)) {
            $this->release(60);

            return;
        }

        $sync->markOutcome(UploadOutcome::Processing);

        ['members' => $members, 'leavers' => $leavers] = $this->rows($sync);
        $altGroups = $this->altGroups($this->changes->alt_groups);

        if ($members === [] && $leavers === [] && $altGroups->isEmpty()) {
            $sync->markOutcome(UploadOutcome::NoChanges);
            SyncCommitted::dispatch($sync);

            return;
        }

        $memberResults = $this->syncMembers($sync, $members);
        $altGroups = $altGroups->concat($this->altGroups(
            collect($this->newlyMatchedAltGroupIds($members, $memberResults))->diff($altGroups->pluck('id'))->values()->all(),
        ));

        $results = [
            ...$memberResults,
            ...$this->syncLeavers($sync, $leavers),
            ...($altGroups->isEmpty() ? [] : LinkAltGroups::run($sync->gameVersion, $altGroups)),
        ];

        DB::transaction(function () use ($sync, $results): void {
            $this->storeResults($sync, $results);
            $sync->markOutcome(UploadOutcome::Processed, $this->counts($results));
        });

        if (collect($results)->contains(fn (RowResultData $result): bool => $result->status === RowStatus::Processed)) {
            Cache::tags(['attendance'])->flush();
            Cache::tags(['characters'])->flush();
        }

        SyncCommitted::dispatch($sync);
    }

    /**
     * Mark the sync as failed, record a failed row for the job, for each changed member and leaver GUID and for each changed alt group,
     * flush the `attendance` and `characters` caches, and fire `SyncCommitted`.
     */
    public function failed(?Throwable $exception): void
    {
        $sync = Upload::findOrFail($this->uploadId);
        $guids = array_values(array_unique([...$this->changes->members, ...$this->changes->leavers, ...$this->changes->departed_members]));
        $names = RosterEntry::where('game_version_id', $this->gameVersionId)
            ->whereIn('kind', [RosterEntryKind::Member, RosterEntryKind::Leaver])
            ->whereIn('key', $guids)
            ->get()
            ->mapWithKeys(fn (RosterEntry $entry): array => [$entry->key => $entry->payload->name]);

        $results = [
            new RowResultData(null, '', '', RowStatus::Failed, RowReason::Error, $exception?->getMessage()),
            ...array_map(fn (string $guid): RowResultData => new RowResultData(null, $guid, $names->get($guid, $guid), RowStatus::Failed, RowReason::Error), $guids),
            ...array_map(fn (string $id): RowResultData => new RowResultData(null, '', '', RowStatus::Failed, RowReason::Error, "alt group {$id} was not linked: {$exception?->getMessage()}"), array_values(array_unique($this->changes->alt_groups))),
        ];

        DB::transaction(function () use ($sync, $results): void {
            $this->storeResults($sync, $results);
            $sync->markOutcome(UploadOutcome::Failed, $this->counts($results));
        });

        Cache::tags(['attendance'])->flush();
        Cache::tags(['characters'])->flush();

        SyncCommitted::dispatch($sync);
    }

    /**
     * Get the outcomes of a sync that has finished processing.
     *
     * @return list<UploadOutcome>
     */
    private function finishedOutcomes(): array
    {
        return [UploadOutcome::Processed, UploadOutcome::NoChanges, UploadOutcome::Failed];
    }

    /**
     * Whether an earlier committed revision of the same game version has not finished processing. An
     * unfinished revision committed more than 7 hours ago no longer counts, and a warning is logged for it.
     */
    private function hasEarlierUnfinishedRevision(Upload $sync): bool
    {
        if ($sync->revision === null) {
            return false;
        }

        $earlier = Upload::select(['grm_uploads.id', 'grm_uploads.revision', 'grm_roster_revisions.committed_at'])
            ->leftJoin('grm_roster_revisions', fn (JoinClause $join) => $join
                ->on('grm_roster_revisions.game_version_id', '=', 'grm_uploads.game_version_id')
                ->on('grm_roster_revisions.revision', '=', 'grm_uploads.revision'))
            ->where('grm_uploads.game_version_id', $sync->game_version_id)
            ->accepted()
            ->whereNotNull('grm_uploads.revision')
            ->where('grm_uploads.revision', '<', $sync->revision)
            ->whereNotIn('grm_uploads.outcome', $this->finishedOutcomes())
            ->get();

        $threshold = now()->subHours(7);
        $isBlocked = false;

        foreach ($earlier as $upload) {
            $committedAt = $upload->committed_at === null ? null : Carbon::parse($upload->committed_at, config('app.timezone'));

            if ($committedAt !== null && $committedAt->lt($threshold)) {
                Log::warning("Skipping stale unfinished GRM sync {$upload->id} (revision {$upload->revision}) for game version {$sync->game_version_id}.");

                continue;
            }

            $isBlocked = true;
        }

        return $isBlocked;
    }

    /**
     * Collect the member and leaver rows to process: the changed and departed rows with the sync's start
     * time, then the retry rows with their entry's commit time.
     *
     * @return array{members: list<array{data: MemberData, startedAt: CarbonInterface, evidenceAt: ?CarbonInterface, isRetry: bool}>, leavers: list<array{data: LeaverData, startedAt: CarbonInterface, evidenceAt: ?CarbonInterface, isRetry: bool}>}
     */
    private function rows(Upload $sync): array
    {
        $changedLeavers = array_values(array_unique([...$this->changes->leavers, ...$this->changes->departed_members]));
        $changedGuids = [...$this->changes->members, ...$changedLeavers];

        $changed = [
            ...$this->entries(RosterEntryKind::Member, $this->changes->members),
            ...$this->entries(RosterEntryKind::Leaver, $changedLeavers),
        ];

        $rows = ['members' => [], 'leavers' => []];

        foreach ($changed as $entry) {
            $rows[$this->rowGroup($entry)][] = $this->row($entry, $sync->captured_at, false);
        }

        $retries = $this->retryEntries($this->retryGuids()->diff($changedGuids)->values()->all());
        $committedAt = RosterRevision::where('game_version_id', $this->gameVersionId)
            ->whereIn('revision', $retries->pluck('revision')->unique()->values())
            ->pluck('committed_at', 'revision');

        foreach ($retries as $entry) {
            $rows[$this->rowGroup($entry)][] = $this->row($entry, $committedAt->get($entry->revision) ?? $sync->captured_at, true);
        }

        return $rows;
    }

    /**
     * Get the GUIDs where any outcome written by the most recent job that processed the GUID was a
     * failure or a warning.
     *
     * @return Collection<int, string>
     */
    private function retryGuids(): Collection
    {
        return UploadRow::where('is_latest', true)
            ->whereIn('status', [RowStatus::Failed, RowStatus::Warning])
            ->whereRelation('upload', 'game_version_id', $this->gameVersionId)
            ->distinct()
            ->pluck('guid')
            ->toBase();
    }

    /**
     * Load each GUID's current member or leaver entry, the one with the newer epoch when both exist.
     *
     * @param  list<string>  $guids
     * @return Collection<int, RosterEntry>
     */
    private function retryEntries(array $guids): Collection
    {
        if ($guids === []) {
            return collect();
        }

        return RosterEntry::where('game_version_id', $this->gameVersionId)
            ->whereIn('kind', [RosterEntryKind::Member, RosterEntryKind::Leaver])
            ->whereIn('key', $guids)
            ->get()
            ->groupBy('key')
            ->map(fn (Collection $entries): RosterEntry => $entries
                ->sortByDesc(fn (RosterEntry $entry): array => [$entry->epoch, $entry->kind === RosterEntryKind::Member ? 1 : 0])
                ->first())
            ->values()
            ->toBase();
    }

    /**
     * Get the alt group IDs of the processed members created in this job or processed from a retry
     * row, whose characters may not have existed when their alt groups were last linked.
     *
     * @param  list<array{data: MemberData, startedAt: CarbonInterface, evidenceAt: ?CarbonInterface, isRetry: bool}>  $rows
     * @param  list<RowResultData>  $results
     * @return list<string>
     */
    private function newlyMatchedAltGroupIds(array $rows, array $results): array
    {
        $retryGuids = collect($rows)->filter(fn (array $row): bool => $row['isRetry'])->map(fn (array $row): string => $row['data']->guid);

        $matchedGuids = collect($results)
            ->filter(fn (RowResultData $result): bool => $result->status === RowStatus::Processed
                && ($result->reason === RowReason::Created || $retryGuids->contains($result->guid)))
            ->pluck('guid');

        return collect($rows)
            ->filter(fn (array $row): bool => $row['data']->alt_group !== null && $matchedGuids->contains($row['data']->guid))
            ->map(fn (array $row): string => $row['data']->alt_group)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Load the alt groups, each with its current payload and its payload before the commit, if the
     * commit changed it.
     *
     * @param  list<string>  $ids
     * @return Collection<int, array{id: string, payload: AltGroupData, previous: ?AltGroupData}>
     */
    private function altGroups(array $ids): Collection
    {
        return collect($this->entries(RosterEntryKind::AltGroup, $ids))
            ->map(fn (RosterEntry $entry): array => [
                'id' => $entry->key,
                'payload' => $entry->payload,
                'previous' => isset($this->changes->previous_alt_groups[$entry->key])
                    ? AltGroupData::from($this->changes->previous_alt_groups[$entry->key])
                    : null,
            ]);
    }

    /**
     * Load the game version's entries of the kind for the keys, in the keys' order.
     *
     * @param  list<string>  $keys
     * @return list<RosterEntry>
     */
    private function entries(RosterEntryKind $kind, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $entries = RosterEntry::where('game_version_id', $this->gameVersionId)
            ->where('kind', $kind)
            ->whereIn('key', $keys)
            ->get()
            ->keyBy('key');

        return collect($keys)->map(fn (string $key): ?RosterEntry => $entries->get($key))->filter()->values()->all();
    }

    /**
     * Build a row to process from an entry, with its raw epoch as evidence.
     *
     * @return array{data: MemberData|LeaverData, startedAt: CarbonInterface, evidenceAt: ?CarbonInterface, isRetry: bool}
     */
    private function row(RosterEntry $entry, CarbonInterface $startedAt, bool $isRetry): array
    {
        return [
            'data' => $entry->payload,
            'startedAt' => $startedAt,
            'evidenceAt' => $entry->epoch > 0 ? CarbonImmutable::createFromTimestampUTC($entry->epoch) : null,
            'isRetry' => $isRetry,
        ];
    }

    /**
     * Get the key of the `rows()` group the entry belongs to.
     */
    private function rowGroup(RosterEntry $entry): string
    {
        return $entry->kind === RosterEntryKind::Member ? 'members' : 'leavers';
    }

    /**
     * Sync each member, then once more each member whose rename clashed, keeping only the second result.
     *
     * @param  list<array{data: MemberData, startedAt: CarbonInterface, evidenceAt: ?CarbonInterface, isRetry: bool}>  $rows
     * @return list<RowResultData>
     */
    private function syncMembers(Upload $sync, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $syncMember = SyncMember::make();
        $gameVersion = $sync->gameVersion;
        /** @var Collection<int, GuildRank> $ranks */
        $ranks = $gameVersion->guildRanks()->get()->keyBy('sort_order');
        $syncRow = fn (array $row): array => $this->failedRowOnException(
            $row['data'],
            fn (): array => $syncMember->handle($gameVersion, $row['data'], $ranks, $row['startedAt'], $row['evidenceAt'], $row['isRetry']),
        );
        $results = [];

        foreach ($rows as $row) {
            $results[] = $syncRow($row);
        }

        foreach ($results as $index => $rowResults) {
            if ($this->hasNameClash($rowResults)) {
                $results[$index] = $syncRow($rows[$index]);
            }
        }

        return array_merge(...$results);
    }

    /**
     * @param  list<array{data: LeaverData, startedAt: CarbonInterface, evidenceAt: ?CarbonInterface, isRetry: bool}>  $rows
     * @return list<RowResultData>
     */
    private function syncLeavers(Upload $sync, array $rows): array
    {
        $syncLeaver = SyncLeaver::make();
        $results = [];

        foreach ($rows as $row) {
            $results = [...$results, ...$this->failedRowOnException(
                $row['data'],
                fn (): array => [$syncLeaver->handle($sync->gameVersion, $row['data'], $row['startedAt'], $row['evidenceAt'], $row['isRetry'])],
            )];
        }

        return $results;
    }

    /**
     * Run a row's sync, or report the exception it throws and return one failed result for the row.
     *
     * @param  callable(): list<RowResultData>  $sync
     * @return list<RowResultData>
     */
    private function failedRowOnException(MemberData|LeaverData $row, callable $sync): array
    {
        try {
            return $sync();
        } catch (Throwable $exception) {
            report($exception);

            return [new RowResultData(null, $row->guid, $row->name, RowStatus::Failed, RowReason::Error, $exception->getMessage())];
        }
    }

    /**
     * @param  list<RowResultData>  $results
     */
    private function hasNameClash(array $results): bool
    {
        return collect($results)->contains(fn (RowResultData $result): bool => $result->status === RowStatus::Failed && $result->reason === RowReason::NameClash);
    }

    /**
     * Store each result as one of the sync's rows. The rows for a GUID become its latest outcome,
     * replacing the rows earlier jobs wrote for it.
     *
     * @param  list<RowResultData>  $results
     */
    private function storeResults(Upload $sync, array $results): void
    {
        $guids = collect($results)->pluck('guid')->filter()->unique()->values();

        if ($guids->isNotEmpty()) {
            UploadRow::where('is_latest', true)
                ->whereIn('guid', $guids)
                ->whereRelation('upload', 'game_version_id', $this->gameVersionId)
                ->update(['is_latest' => false]);
        }

        $sync->rows()->createMany(array_map(fn (RowResultData $result): array => [
            'character_id' => $result->characterId,
            'guid' => $result->guid,
            'name' => $result->name,
            'status' => $result->status,
            'reason' => $result->reason,
            'message' => $result->message,
            'is_latest' => $result->guid !== '',
        ], $results));
    }

    /**
     * Count the results by status.
     *
     * @param  list<RowResultData>  $results
     * @return array{processed_count: int, skipped_count: int, warning_count: int, error_count: int}
     */
    private function counts(array $results): array
    {
        $counts = collect($results)->countBy(fn (RowResultData $result): string => $result->status->value);

        return [
            'processed_count' => $counts->get(RowStatus::Processed->value, 0),
            'skipped_count' => $counts->get(RowStatus::Skipped->value, 0),
            'warning_count' => $counts->get(RowStatus::Warning->value, 0),
            'error_count' => $counts->get(RowStatus::Failed->value, 0),
        ];
    }
}
