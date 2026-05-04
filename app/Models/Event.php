<?php

namespace App\Models;

use App\Http\Resources\EventCollection;
use App\Services\Discord\Discord;
use Illuminate\Database\Eloquent\Attributes\UseResourceCollection;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[UseResourceCollection(EventCollection::class)]
class Event extends Model
{
    use HasFactory, HasUuids;

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
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
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
    ];

    // ========== Custom attributes ============

    /**
     * Get the Discord channel associated with the event.
     */
    protected function channel(): Attribute
    {
        return Attribute::make(
            get: fn () => app(Discord::class)->getChannel($this->channel_id),
        )->shouldCache();
    }

    /**
     * Get the duration of the event in seconds.
     */
    protected function duration(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->start_time->diffInSeconds($this->end_time),
        );
    }

    // ========== Relationships ============

    /**
     * The characters that are associated with the event.
     */
    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class, 'pivot_events_characters', 'event_id', 'character_id')
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

    /**
     * The loot masters that are associated with the event.
     */
    public function lootMasters(): BelongsToMany
    {
        return $this->characters()->wherePivot('is_loot_master', true);
    }

    /**
     * The raids that are associated with the event.
     */
    public function raids(): BelongsToMany
    {
        return $this->belongsToMany(Raid::class, 'pivot_events_raids', 'event_id', 'raid_id')
            ->withTimestamps();
    }
}
