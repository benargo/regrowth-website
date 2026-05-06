<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EventCharacter extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pivot_events_characters';

    /**
     * The model's default attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_confirmed' => false,
        'is_leader' => false,
        'is_loot_councillor' => false,
        'is_loot_master' => false,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'event_id',
        'character_id',
        'slot_number',
        'group_number',
        'is_confirmed',
        'is_leader',
        'is_loot_councillor',
        'is_loot_master',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'slot_number' => 'integer',
        'group_number' => 'integer',
        'is_confirmed' => 'boolean',
        'is_leader' => 'boolean',
        'is_loot_councillor' => 'boolean',
        'is_loot_master' => 'boolean',
    ];
}
