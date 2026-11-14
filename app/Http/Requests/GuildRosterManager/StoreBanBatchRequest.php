<?php

namespace App\Http\Requests\GuildRosterManager;

use App\Actions\GuildRosterManager\ListExistingBanGuids;
use App\Enums\GuildRosterManager\RosterEntryKind;
use App\Models\GuildRosterManager\Ban;
use App\Models\GuildRosterManager\Upload;

/**
 * Validates a batch of ban rows, which also needs the ban permissions: create for a new ban and update for an existing one.
 * A ban exists when the state holds its roster entry.
 */
class StoreBanBatchRequest extends StoreSyncBatchRequest
{
    public function kind(): RosterEntryKind
    {
        return RosterEntryKind::Ban;
    }

    protected function rowRules(): array
    {
        return [
            'guid' => ['required', ...self::GUID_RULES, 'distinct'],
            'name' => ['required', 'string', 'max:64'],
            'unbanned' => ['required', 'boolean'],
            'bannedAt' => ['nullable', 'date_format:Y-m-d\TH:i:s\Z'],
            'bannedBy' => ['nullable', 'string', 'max:64'],
            'reason' => ['nullable', 'string', 'max:75'],
            'epoch' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    /**
     * Determine whether the user may write to the sync and may create or update every ban in the batch.
     */
    public function authorize(): bool
    {
        if (! parent::authorize()) {
            return false;
        }

        $user = $this->user();
        $mayCreate = $user->can('create', Ban::class);
        $mayUpdate = $user->can('update', Ban::class);

        if (! $mayCreate && ! $mayUpdate) {
            return false;
        }

        $guids = collect($this->rowsInput())
            ->take(self::MAX_ROWS + 1)
            ->map(fn (mixed $row): mixed => is_array($row) ? ($row['guid'] ?? null) : null)
            ->filter(fn (mixed $guid): bool => is_string($guid))
            ->values();

        /** @var Upload $sync */
        $sync = $this->route('sync');

        $existing = ListExistingBanGuids::run($sync->game_version_id, $guids)->flip();

        return $guids->every(fn (string $guid): bool => $existing->has($guid) ? $mayUpdate : $mayCreate);
    }
}
