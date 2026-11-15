<?php

namespace App\Actions\GuildRosterManager;

use App\Casts\AsRosterEntryData;
use App\Contracts\Data\GuildRosterManager\RosterEntryData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Models\GuildRosterManager\SyncRow;
use App\Models\GuildRosterManager\Upload;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Stages a batch of rows of one kind for a sync, replacing rows the sync already holds under the same key.
 * Refuses a batch that takes the sync past the kind's row limit.
 */
class StageSyncRows
{
    use AsAction;

    /**
     * @param  array<int, RosterEntryData>  $rows
     * @return int The number of rows in the batch.
     *
     * @throws ValidationException
     */
    public function handle(Upload $sync, RosterEntryKind $kind, array $rows): int
    {
        $keys = array_map(fn (RosterEntryData $row): string => $row->key(), $rows);

        $this->ensureWithinLimit($sync, $kind, $keys);

        $payloads = array_map(fn (RosterEntryData $row, string $key): array => [
            'upload_id' => $sync->id,
            'kind' => $kind->value,
            'key' => $key,
            'payload' => AsRosterEntryData::serialize($row),
            'epoch' => $row->epoch,
        ], $rows, $keys);

        SyncRow::upsert($payloads, ['upload_id', 'kind', 'key'], ['payload', 'epoch', 'updated_at']);

        return count($rows);
    }

    /**
     * @param  array<int, string>  $keys
     *
     * @throws ValidationException
     */
    private function ensureWithinLimit(Upload $sync, RosterEntryKind $kind, array $keys): void
    {
        $limit = $kind->maxRows();

        if ($limit === null) {
            return;
        }

        $batchKeys = array_values(array_unique($keys));
        $otherStagedCount = SyncRow::where('upload_id', $sync->id)
            ->where('kind', $kind)
            ->whereNotIn('key', $batchKeys)
            ->count();

        if ($otherStagedCount + count($batchKeys) > $limit) {
            throw ValidationException::withMessages([
                'rows' => "The sync already holds the maximum number of {$kind->value} rows.",
            ]);
        }
    }
}
