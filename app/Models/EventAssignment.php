<?php

namespace App\Models;

use App\Casts\AsClassName;
use App\Http\Resources\EventAssignmentResource;
use App\Models\Concerns\FlushesRaidingCacheOnSave;
use Database\Factories\EventAssignmentFactory;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Database\Eloquent\BroadcastableModelEventOccurred;
use Illuminate\Database\Eloquent\BroadcastsEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAssignment extends Model
{
    /** @use HasFactory<EventAssignmentFactory> */
    use BroadcastsEvents, FlushesRaidingCacheOnSave, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'boss_id',
        'group_id',
        'sort_order',
        'left_type',
        'left_value',
        'right_type',
        'right_value',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'sort_order' => 'integer',
        'left_type' => AsClassName::class,
        'right_type' => AsClassName::class,
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
            'created' => 'EventAssignmentCreated',
            'updated' => 'EventAssignmentUpdated',
            'deleted' => 'EventAssignmentDeleted',
            default => 'EventAssignment'.ucfirst($event),
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
            'assignment' => array_merge(
                EventAssignmentResource::make($this)->resolve(),
                [
                    'boss_id' => $this->boss_id,
                    'group_id' => $this->group_id,
                    'left_type' => $this->getRawOriginal('left_type'),
                    'left_value' => $this->left_value,
                    'right_type' => $this->getRawOriginal('right_type'),
                    'right_value' => $this->right_value,
                ],
            ),
        ];
    }

    protected function newBroadcastableEvent(string $event): BroadcastableModelEventOccurred
    {
        return tap(new BroadcastableModelEventOccurred($this, $event), function ($broadcastEvent) {
            $broadcastEvent->dontBroadcastToCurrentUser();
        });
    }

    // ============ Invariant helpers ============

    /**
     * Returns true if both left_value and right_value are non-empty strings.
     */
    public function isValid(): bool
    {
        return filled($this->left_value) && filled($this->right_value);
    }

    // ============ Resolvers ============

    /**
     * Resolves the left side to its Eloquent model instance or returns the raw string value.
     */
    public function resolveLeft(): Model|string|null
    {
        return $this->resolveSide($this->left_type, $this->left_value);
    }

    /**
     * Resolves the right side to its Eloquent model instance or returns the raw string value.
     */
    public function resolveRight(): Model|string|null
    {
        return $this->resolveSide($this->right_type, $this->right_value);
    }

    /**
     * @param  class-string<Model>|null  $type
     */
    private function resolveSide(?string $type, ?string $value): Model|string|null
    {
        if ($value === null) {
            return null;
        }

        if ($type === null || ! class_exists($type)) {
            return $value;
        }

        $instance = new $type;

        return $type::query()->where($instance->getKeyName(), $value)->first() ?? $value;
    }

    // ============ Relationships ============

    /**
     * The event this assignment belongs to.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The boss this assignment belongs to, if any.
     *
     * @return BelongsTo<Boss, $this>
     */
    public function boss(): BelongsTo
    {
        return $this->belongsTo(Boss::class);
    }

    /**
     * The group this assignment belongs to, if any.
     *
     * @return BelongsTo<EventAssignmentGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(EventAssignmentGroup::class, 'group_id');
    }
}
