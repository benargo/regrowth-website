<?php

namespace App\Models;

use App\Http\Integrations\Discord\Enums\ChannelType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

/**
 * This model must NOT use Spatie's `SortableTrait`. The `position` column
 * and every other synced column reflect the channel as Discord reports it
 * and are set by Discord, not by this application.
 */
#[Fillable(['id', 'name', 'type', 'position', 'parent_id', 'topic', 'nsfw'])]
#[Table(keyType: 'string', incrementing: false)]
class DiscordChannel extends Model
{
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'position' => 'integer',
            'nsfw' => 'boolean',
        ];
    }

    /**
     * Scope the query to stub rows that have not been synced from Discord.
     */
    #[Scope]
    protected function stubs(Builder $query): void
    {
        $query->whereNull('type');
    }

    /**
     * Scope the query to thread channels.
     */
    #[Scope]
    protected function threads(Builder $query): void
    {
        $query->whereIn('type', ChannelType::threadTypes());
    }

    /**
     * Scope the query to channels that are not threads, including stubs.
     */
    #[Scope]
    protected function withoutThreads(Builder $query): void
    {
        $query->where(function (Builder $query): void {
            $query->whereNull('type')->orWhereNotIn('type', ChannelType::threadTypes());
        });
    }

    /**
     * @return HasMany<DiscordChannel, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(DiscordChannel::class, 'parent_id');
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'channel_id');
    }

    /**
     * @return HasMany<DiscordMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(DiscordMessage::class, 'channel_id');
    }

    /**
     * @return BelongsTo<DiscordChannel, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DiscordChannel::class, 'parent_id');
    }
}
