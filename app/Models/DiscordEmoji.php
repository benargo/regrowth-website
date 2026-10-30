<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['id', 'name', 'animated', 'available'])]
#[Table(name: 'discord_emoji', keyType: 'string', incrementing: false)]
class DiscordEmoji extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'animated' => 'boolean',
            'available' => 'boolean',
        ];
    }

    /**
     * The Discord CDN URL of the emoji image (GIF when animated, otherwise PNG).
     *
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn (): string => sprintf(
            'https://cdn.discordapp.com/emojis/%s.%s',
            $this->id,
            $this->animated ? 'gif' : 'png',
        ));
    }
}
