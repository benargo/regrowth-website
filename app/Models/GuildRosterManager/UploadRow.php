<?php

namespace App\Models\GuildRosterManager;

use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use App\Models\Character;
use Database\Factories\GuildRosterManager\UploadRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The outcome of processing one member row of a Guild Roster Manager upload.
 */
#[Fillable(['upload_id', 'character_id', 'guid', 'name', 'status', 'reason', 'message', 'is_latest'])]
#[Table('grm_upload_rows')]
class UploadRow extends Model
{
    /** @use HasFactory<UploadRowFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RowStatus::class,
            'reason' => RowReason::class,
            'is_latest' => 'boolean',
        ];
    }

    // ============ Relationships ============

    /**
     * Get the character the row was applied to, if any.
     *
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * Get the upload the row belongs to.
     *
     * @return BelongsTo<Upload, $this>
     */
    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }
}
