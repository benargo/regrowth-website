<?php

namespace App\Models\GuildRosterManager;

use App\Enums\GuildRosterManager\UploadOutcome;
use App\Enums\GuildRosterManager\UploadSource;
use App\Models\DesktopClient;
use App\Models\GameVersion;
use App\Models\User;
use App\Policies\DesktopSyncPolicy;
use Database\Factories\GuildRosterManager\UploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A guild roster upload from Guild Roster Manager, received from the desktop app or added manually.
 */
#[Fillable([
    'game_version_id',
    'user_id',
    'source',
    'desktop_client_id',
    'idempotency_key',
    'outcome',
    'base_revision',
    'revision',
    'expires_at',
    'ticket_expires_at',
    'addon_version',
    'captured_at',
    'member_count',
    'storage_path',
    'processed_at',
    'processed_count',
    'skipped_count',
    'warning_count',
    'error_count',
])]
#[Table('grm_uploads')]
#[UsePolicy(DesktopSyncPolicy::class)]
class Upload extends Model
{
    /** @use HasFactory<UploadFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => UploadSource::class,
            'outcome' => UploadOutcome::class,
            'base_revision' => 'integer',
            'revision' => 'integer',
            'captured_at' => 'datetime',
            'processed_at' => 'datetime',
            'processed_count' => 'integer',
            'skipped_count' => 'integer',
            'warning_count' => 'integer',
            'error_count' => 'integer',
            'member_count' => 'integer',
            'expires_at' => 'datetime',
            'ticket_expires_at' => 'datetime',
        ];
    }

    /**
     * Get the desktop client the upload was received from.
     *
     * @return BelongsTo<DesktopClient, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(DesktopClient::class, 'desktop_client_id');
    }

    /**
     * Get the game version the upload belongs to.
     *
     * @return BelongsTo<GameVersion, $this>
     */
    public function gameVersion(): BelongsTo
    {
        return $this->belongsTo(GameVersion::class);
    }

    /**
     * Get the per-row outcomes recorded for the upload.
     *
     * @return HasMany<UploadRow, $this>
     */
    public function rows(): HasMany
    {
        return $this->hasMany(UploadRow::class);
    }

    /**
     * Get the rows staged for the upload.
     *
     * @return HasMany<SyncRow, $this>
     */
    public function syncRows(): HasMany
    {
        return $this->hasMany(SyncRow::class);
    }

    /**
     * Get the user who made the upload.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limit to uploads that count as committed, excluding superseded, staging, abandoned and expired ones.
     *
     * @param  Builder<Upload>  $query
     */
    #[Scope]
    protected function accepted(Builder $query): void
    {
        $query->whereNotIn('outcome', [
            UploadOutcome::Superseded,
            UploadOutcome::Staging,
            UploadOutcome::Abandoned,
            UploadOutcome::Expired,
        ]);
    }

    /**
     * Limit to syncs that are still being staged.
     *
     * @param  Builder<Upload>  $query
     */
    #[Scope]
    protected function staging(Builder $query): void
    {
        $query->where('outcome', UploadOutcome::Staging);
    }

    /**
     * Whether the sync has expired, or is staging past its expiry time.
     *
     * @return Attribute<bool, never>
     */
    protected function isExpired(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->outcome === UploadOutcome::Expired
                || ($this->outcome === UploadOutcome::Staging
                    && $this->expires_at !== null
                    && $this->expires_at->lte(now())),
        );
    }

    /**
     * Whether the staging sync's ticket is missing or has lapsed.
     *
     * @return Attribute<bool, never>
     */
    protected function isTicketExpired(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->outcome === UploadOutcome::Staging
                && ($this->ticket_expires_at === null || $this->ticket_expires_at->lte(now())),
        );
    }

    /**
     * Set the upload's outcome, along with any other attributes, and save it. `Processed` and `NoChanges`
     * also set `processed_at` to now, unless the attributes set it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function markOutcome(UploadOutcome $outcome, array $attributes = []): void
    {
        if (in_array($outcome, [UploadOutcome::Processed, UploadOutcome::NoChanges], true) && ! array_key_exists('processed_at', $attributes)) {
            $attributes['processed_at'] = now();
        }

        $this->forceFill(['outcome' => $outcome, ...$attributes])->save();
    }
}
