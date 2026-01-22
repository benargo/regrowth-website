<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class GuildConfig extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'realm_slug',
        'region',
        'warcraftlogs_guild_id',
        'blizzard_guild_id',
        'blizzard_realm_id',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'warcraftlogs_guild_id' => 'int',
        'blizzard_guild_id'     => 'int',
        'blizzard_realm_id'     => 'int',
    ];

    protected function blizzardNamespace(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => Str::lower($value)
        );
    }

    protected function region(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => Str::lower($value)
        );
    }

    protected function realmSlug(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => Str::slug($value, '-')
        );
    }

    /**
     * Accessor for the realm name.
     * Converts the realm slug into a human-readable format.
     */
    protected function realmName(): Attribute
    {
        return Attribute::make(
            get: function () {
                return collect(explode('-', $this->realm_slug))
                    ->map(fn($word) => ucfirst($word))
                    ->implode(' ');
            }
        );
    }   
}
