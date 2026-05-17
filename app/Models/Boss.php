<?php

namespace App\Models;

use App\Http\Resources\BossResource;
use App\Models\Concerns\FlushesRaidingCacheOnSave;
use App\Models\LootCouncil\Comment;
use App\Models\LootCouncil\Item;
use Database\Factories\BossFactory;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\BroadcastableModelEventOccurred;
use Illuminate\Database\Eloquent\BroadcastsEvents;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Boss extends Model implements HasMedia
{
    /** @use HasFactory<BossFactory> */
    use BroadcastsEvents, FlushesRaidingCacheOnSave, HasFactory, InteractsWithMedia;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'bosses';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'raid_id',
        'encounter_order',
        'notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = ['created_at', 'updated_at'];

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
     * @return HasManyThrough<Comment>
     */
    public function comments(): HasManyThrough
    {
        return $this->hasManyThrough(Comment::class, Item::class, 'boss_id', 'item_id');
    }
}
