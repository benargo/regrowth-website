<?php

namespace App\Models\Raids;

use App\Models\Character;
use App\Services\Discord\Stubs\ChannelStub;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Event extends Model
{
    use HasFactory, HasUuids;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'raid_events';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id',
        'raid_helper_event_id',
        'title',
        'start_time',
        'end_time',
        'channel_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'channel_id' => ChannelStub::class,
    ];

    /**
     * The characters that are associated with the event.
     */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'pivot_raid_events_characters', 'event_id', 'character_id')
            ->using(EventCharacter::class)
            ->withPivot(['slot_number', 'group_number', 'is_confirmed', 'is_leader', 'is_loot_councillor', 'is_loot_master'])
            ->withTimestamps();
    }

    /**
     * The leaders that are associated with the event.
     */
    public function leaders(): BelongsToMany
    {
        return $this->characters()->wherePivot('is_leader', true);
    }

    /**
     * The loot councillors that are associated with the event.
     */
    public function lootCouncillors(): BelongsToMany
    {
        return $this->characters()->wherePivot('is_loot_councillor', true);
    }
}
