<?php

namespace App\Models;

use Database\Factories\PlayableRaceFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[WithoutIncrementing]
#[WithoutTimestamps]
class PlayableRace extends Model
{
    /** @use HasFactory<PlayableRaceFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'name',
    ];

    /**
     * Get the characters of this playable race.
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }
}
