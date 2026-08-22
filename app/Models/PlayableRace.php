<?php

namespace App\Models;

use App\Enums\Faction;
use Database\Factories\PlayableRaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[WithoutIncrementing]
#[WithoutTimestamps]
#[Fillable(['id', 'name', 'faction'])]
class PlayableRace extends Model
{
    /** @use HasFactory<PlayableRaceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'faction' => Faction::class,
        ];
    }

    /**
     * Get the characters of this playable race.
     */
    public function characters(): HasMany
    {
        return $this->hasMany(Character::class);
    }
}
