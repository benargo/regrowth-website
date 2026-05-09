<?php

namespace App\Models;

use Database\Factories\EventAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAssignment extends Model
{
    /** @use HasFactory<EventAssignmentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'boss_id',
        'group_id',
        'sort_order',
        'left_model_key',
        'left_value',
        'right_model_key',
        'right_value',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Maps model_key strings to their Eloquent model classes.
     *
     * @var array<string, class-string<Model>>
     */
    private const array MODEL_MAP = [
        'character' => Character::class,
        'spell' => Spell::class,
        'target_marker' => TargetMarker::class,
    ];

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
        return $this->resolveSide($this->left_model_key, $this->left_value);
    }

    /**
     * Resolves the right side to its Eloquent model instance or returns the raw string value.
     */
    public function resolveRight(): Model|string|null
    {
        return $this->resolveSide($this->right_model_key, $this->right_value);
    }

    /**
     * @param  class-string<Model>|null  $modelKey
     */
    private function resolveSide(?string $modelKey, string $value): Model|string
    {
        if ($modelKey === null || ! isset(self::MODEL_MAP[$modelKey])) {
            return $value;
        }

        $modelClass = self::MODEL_MAP[$modelKey];
        $instance = new $modelClass;

        return $modelClass::query()->where($instance->getKeyName(), $value)->first() ?? $value;
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
