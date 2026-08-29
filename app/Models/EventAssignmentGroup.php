<?php

namespace App\Models;

use App\Models\Concerns\FlushesRaidingCacheOnSave;
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
use Illuminate\Support\Str;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

#[Fillable(['event_id', 'boss_id', 'name', 'notes', 'sort_order'])]
#[Hidden(['event_id', 'created_at', 'updated_at'])]
class EventAssignmentGroup extends Model implements Sortable
{
    use BroadcastsEvents, FlushesRaidingCacheOnSave, HasFactory, SortableTrait;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'name' => 'New group',
    ];

    // ============ Sorting ============

    /**
     * Scope sort_order to a single event so reordering one event's groups never
     * renumbers another's.
     */
    public function buildSortQuery(): Builder
    {
        return static::query()->where('event_id', $this->event_id);
    }

    /**
     * Only auto-assign sort_order when the caller hasn't set one. Callers that
     * assign a value themselves (e.g. EventController::applyTemplate, factories)
     * need that value to survive create() rather than be overwritten.
     */
    public function shouldSortWhenCreating(): bool
    {
        return $this->sort_order === null;
    }

    // ============ Broadcasting ============

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(string $event): array
    {
        return [new PrivateChannel("event.{$this->event_id}")];
    }

    public function broadcastAs(string $event): string
    {
        return match ($event) {
            'created' => 'EventGroupCreated',
            'updated' => 'EventGroupUpdated',
            'deleted' => 'EventGroupDeleted',
            default => 'EventGroup'.ucfirst($event),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(string $event): array
    {
        if ($event === 'deleted') {
            return ['id' => $this->id];
        }

        return [
            'group' => [
                'id' => $this->id,
                'name' => $this->name,
                'sort_order' => $this->sort_order,
                'boss_id' => $this->boss_id ?? null,
            ],
        ];
    }

    protected function newBroadcastableEvent(string $event): BroadcastableModelEventOccurred
    {
        return tap(new BroadcastableModelEventOccurred($this, $event), function ($broadcastEvent) {
            $broadcastEvent->dontBroadcastToCurrentUser();
        });
    }

    // ========== Custom attributes ===========

    /**
     * Format the notes attribute as markdown.
     *
     * @return Attribute<string|null>
     */
    protected function notes(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Str::markdown($value) : null,
        )->shouldCache();
    }

    // ============ Relationships ============

    /**
     * The event this group belongs to.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The boss this group belongs to, if any.
     */
    public function boss(): BelongsTo
    {
        return $this->belongsTo(Boss::class);
    }

    /**
     * The assignments that belong to this group.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(EventAssignment::class, 'group_id');
    }
}
