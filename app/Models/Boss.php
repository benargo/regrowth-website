<?php

namespace App\Models;

use App\Contracts\Models\BelongsToGameVersion;
use App\Contracts\Models\DatasetModel;
use App\Contracts\Models\DispatchesChangesForMedia;
use App\Contracts\Models\EditLockable;
use App\Contracts\Models\HasUsage;
use App\Events\RaidingDataChanged;
use App\Http\Resources\BossResource;
use App\Models\Concerns\HasEditLock;
use App\Models\Concerns\InteractsWithChaperonedMedia;
use App\Models\Concerns\TracksUsage;
use App\Policies\DatasetPolicy;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\BroadcastableModelEventOccurred;
use Illuminate\Database\Eloquent\BroadcastsEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;

#[Fillable(['name', 'sort_order', 'notes'])]
#[Hidden(['created_at', 'updated_at'])]
#[UsePolicy(DatasetPolicy::class)]
class Boss extends Model implements BelongsToGameVersion, DatasetModel, DispatchesChangesForMedia, EditLockable, HasMedia, HasUsage, Sortable
{
    use BroadcastsEvents, HasEditLock, HasFactory, InteractsWithChaperonedMedia, SortableTrait, TracksUsage;

    /**
     * The event map for the model.
     *
     * @var array<string, string>
     */
    protected $dispatchesEvents = [
        'created' => RaidingDataChanged::class,
        'updated' => RaidingDataChanged::class,
        'deleted' => RaidingDataChanged::class,
    ];

    /**
     * Relationships whose existing rows mark this boss as in use.
     *
     * @return list<string>
     */
    public function usageRelations(): array
    {
        return ['items', 'upcomingAssignmentGroups', 'upcomingAssignments', 'upcomingEvents'];
    }

    /**
     * The event dispatched with this model when its media changes.
     *
     * @return class-string<RaidingDataChanged>
     */
    public function mediaChangedEvent(): string
    {
        return RaidingDataChanged::class;
    }

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
     * Broadcasts `BossStrategyChanged` on update only.
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
     * Get the game version key of the boss's raid's phase, or null when it has none.
     *
     * @return Attribute<int|null>
     */
    protected function gameVersionId(): Attribute
    {
        return Attribute::make(
            get: fn (): ?int => $this->raid?->phase?->game_version_id,
        );
    }

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
     * Get the assignment groups for this boss.
     *
     * @return HasMany<EventAssignmentGroup, $this>
     */
    public function assignmentGroups(): HasMany
    {
        return $this->hasMany(EventAssignmentGroup::class);
    }

    /**
     * Get the assignment groups for this boss in events that have not ended.
     *
     * @return HasMany<EventAssignmentGroup, $this>
     */
    public function upcomingAssignmentGroups(): HasMany
    {
        return $this->assignmentGroups()->whereHas('event', fn (Builder $query) => $query->where('end_time', '>=', now()));
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
     * Get the assignments for this boss in events that have ended.
     *
     * @return HasMany<EventAssignment, $this>
     */
    public function pastAssignments(): HasMany
    {
        return $this->assignments()->whereHas('event', fn (Builder $query) => $query->where('end_time', '<', now()));
    }

    /**
     * Get the assignments for this boss in events that have not ended.
     *
     * @return HasMany<EventAssignment, $this>
     */
    public function upcomingAssignments(): HasMany
    {
        return $this->assignments()->whereHas('event', fn (Builder $query) => $query->where('end_time', '>=', now()));
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

    /**
     * Get the events and event templates that include this boss.
     *
     * @return BelongsToMany<Event, $this>
     */
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'pivot_events_bosses', 'boss_id', 'event_id')
            ->using(EventBoss::class)
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * Get the events that include this boss and have ended.
     *
     * @return BelongsToMany<Event, $this>
     */
    public function pastEvents(): BelongsToMany
    {
        return $this->events()->where('events.end_time', '<', now());
    }

    /**
     * Get the event templates that include this boss.
     *
     * @return BelongsToMany<Event, $this>
     */
    public function templateEvents(): BelongsToMany
    {
        return $this->events()->templates();
    }

    /**
     * Get the events that include this boss and have not ended.
     *
     * @return BelongsToMany<Event, $this>
     */
    public function upcomingEvents(): BelongsToMany
    {
        return $this->events()->where('events.end_time', '>=', now());
    }

    /**
     * Get the items that drop from this boss.
     *
     * @return HasMany<Item, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'boss_id');
    }

    /**
     * Get the raid that this boss belongs to.
     *
     * @return BelongsTo<Raid, $this>
     */
    public function raid(): BelongsTo
    {
        return $this->belongsTo(Raid::class);
    }
}
