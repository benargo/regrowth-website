<?php

namespace App\Models;

use App\Casts\AsClassName;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable(['type', 'channel_id', 'message_id', 'payload', 'created_by_user_id'])]
#[Hidden(['updated_at', 'deleted_at'])]
class DiscordMessage extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AsClassName::class,
            'payload' => MessagePayloadData::class,
        ];
    }

    /**
     * Get the Discord channel the message was posted in, including a soft-deleted one.
     *
     * @return BelongsTo<DiscordChannel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(DiscordChannel::class, 'channel_id')->withTrashed();
    }

    /**
     * Get the user who created this message.
     *
     * A null value indicates that the message was created by the system rather than a specific user.
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Get the related model entries for this message.
     *
     * @return HasMany<DiscordMessageRelatedModel, $this>
     */
    public function relatedModels(): HasMany
    {
        return $this->hasMany(DiscordMessageRelatedModel::class);
    }

    /**
     * Upsert the given related-model rows and delete every other row for this message, in one transaction.
     *
     * @param  list<array{model_id: int|string, model_type: string}>  $relatedModels
     */
    public function syncRelatedModels(array $relatedModels): void
    {
        $desired = array_map(fn (array $entry): array => [
            'discord_message_id' => $this->id,
            'model_type' => $entry['model_type'],
            'model_id' => (string) $entry['model_id'],
        ], $relatedModels);

        DB::transaction(function () use ($desired): void {
            if ($desired === []) {
                $this->relatedModels()->delete();

                return;
            }

            $this->relatedModels()->upsert(
                $desired,
                ['discord_message_id', 'model_type', 'model_id'],
                ['discord_message_id'],
            );

            $keepIds = $this->relatedModels()
                ->where(function ($query) use ($desired) {
                    foreach ($desired as $row) {
                        $query->orWhere(function ($subQuery) use ($row) {
                            $subQuery
                                ->where('model_type', $row['model_type'])
                                ->where('model_id', $row['model_id']);
                        });
                    }
                })
                ->pluck('id');

            $this->relatedModels()
                ->whereNotIn('id', $keepIds)
                ->delete();
        });
    }

    /**
     * Scope to messages linked to the given model through their related-model rows.
     */
    #[Scope]
    protected function relatedTo(Builder $query, string $modelType, int|string $modelId): void
    {
        $query->whereHas('relatedModels', fn (Builder $related) => $related
            ->where('model_type', $modelType)
            ->where('model_id', (string) $modelId)
        );
    }
}
