<?php

namespace App\Models;

use App\Models\Concerns\FlushesRaidingCacheOnSave;
use Database\Factories\EventAssignmentGroupFactory;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\BroadcastableModelEventOccurred;
use Illuminate\Database\Eloquent\BroadcastsEvents;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EventAssignmentGroup extends Model
{
    /** @use HasFactory<EventAssignmentGroupFactory> */
    use BroadcastsEvents, FlushesRaidingCacheOnSave, HasFactory;

    /**
     * The model's default values.
     */
    protected $attributes = [
        'name' => 'New group',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'boss_id',
        'name',
        'notes',
        'sort_order',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'event_id',
        'created_at',
        'updated_at',
    ];

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
