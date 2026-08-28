<?php

namespace App\Models;

use App\Http\Resources\BossResource;
use App\Models\Concerns\FlushesRaidingCacheOnSave;
use App\Models\Concerns\SortsExplicitlyOnCreate;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\BroadcastableModelEventOccurred;
use Illuminate\Database\Eloquent\BroadcastsEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use Spatie\EloquentSortable\Sortable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['name', 'raid_id', 'sort_order', 'notes'])]
#[Hidden(['created_at', 'updated_at'])]
class Boss extends Model implements HasMedia, Sortable
{
    use BroadcastsEvents, FlushesRaidingCacheOnSave, HasFactory, InteractsWithMedia, SortsExplicitlyOnCreate;

    // ============ Sorting ============

    public function buildSortQuery(): Builder
    {
        return static::query()->where('raid_id', $this->raid_id);
    }

    // ============ Casting ============

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    // ============ Broadcasting ============

    /**
     * Only broadcast on update events — boss creation/deletion is managed in seeders/migrations.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(string $event): array
    {
        return $event === 'updated' ? [new PrivateChannel("boss.{$this->id}")] : [];
    }

    public function broadcastAs(string $event): string
    {
        return $event === 'updated' ? 'BossStrategyChanged' : 'Boss'.ucfirst($event);
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(string $event): array
    {
        return ['boss' => BossResource::make($this)->resolve()];
    }

    protected function newBroadcastableEvent(string $event): BroadcastableModelEventOccurred
    {
        return tap(new BroadcastableModelEventOccurred($this, $event), function ($broadcastEvent) {
            $broadcastEvent->dontBroadcastToCurrentUser();
        });
    }

    // ============ Custom attributes ===========

    /**
     * Get the slug for the boss, generated from the name.
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::slug($this->name),
        )->shouldCache();
    }

    // ============ Relationships ===========

    /**
     * Get the raid that this boss belongs to.
     *
     * @return BelongsTo<Raid, $this>
     */
    public function raid(): BelongsTo
    {
        return $this->belongsTo(Raid::class);
    }

    /**
     * Get the assignments associated with this boss.
     *
     * @return HasMany<EventAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EventAssignment::class);
    }

    /**
     * Get the items that drop from this boss.
     *
     * @return HasMany<Item>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'boss_id');
    }

    /**
     * Get the comments for the items that drop from this boss.
     *
     * @return HasManyThrough<Comment, Item, $this>
     */
    public function comments(): HasManyThrough
    {
        return $this->hasManyThrough(Comment::class, Item::class, 'boss_id', 'commentable_id')
            ->where('commentable_type', Item::class);
    }
}
