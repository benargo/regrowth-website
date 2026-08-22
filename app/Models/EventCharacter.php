<?php

namespace App\Models;

use App\Enums\SignupStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Table('pivot_events_characters')]
#[Fillable('event_id', 'character_id', 'slot_number', 'group_number', 'signup_status', 'is_leader', 'is_loot_councillor', 'is_loot_master', 'is_benched')]
#[Hidden('created_at')]
class EventCharacter extends Pivot
{
    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'signup_status' => SignupStatus::Unconfirmed->value,
        'is_leader' => false,
        'is_loot_councillor' => false,
        'is_loot_master' => false,
        'is_benched' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_number' => 'integer',
            'group_number' => 'integer',
            'signup_status' => SignupStatus::class,
            'is_leader' => 'boolean',
            'is_loot_councillor' => 'boolean',
            'is_loot_master' => 'boolean',
            'is_benched' => 'boolean',
        ];
    }

    // ========== Relationships ============

    /**
     * Get the event associated with this pivot record.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Get the character associated with this pivot record.
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }
}
