<?php

namespace App\Models\GuildRosterManager;

use App\Casts\AsRosterEntryData;
use App\Enums\GuildRosterManager\RosterEntryKind;
use Database\Factories\GuildRosterManager\SyncRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A row staged by a desktop sync until the sync is committed.
 */
#[Fillable([
    'upload_id',
    'kind',
    'key',
    'payload',
    'epoch',
])]
#[Table('grm_sync_rows')]
class SyncRow extends Model
{
    /** @use HasFactory<SyncRowFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => RosterEntryKind::class,
            'payload' => AsRosterEntryData::class,
            'epoch' => 'integer',
        ];
    }

    /**
     * Get the upload the row was staged for.
     *
     * @return BelongsTo<Upload, $this>
     */
    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
