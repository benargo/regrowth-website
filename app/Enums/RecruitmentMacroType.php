<?php

namespace App\Enums;

use App\Actions\Handbook\CountDiscordCharacters;

enum RecruitmentMacroType: string
{
    case InGame = 'in_game';
    case Discord = 'discord';

    /**
     * Get the human-readable name of the macro type.
     */
    public function label(): string
    {
        return match ($this) {
            self::InGame => 'In-game chat',
            self::Discord => 'Discord',
        };
    }

    /**
     * Get the maximum length of a macro of this type.
     */
    public function characterLimit(): int
    {
        return match ($this) {
            self::InGame => 255,
            self::Discord => 2000,
        };
    }

    /**
     * Count the content's length in the unit this type's limit uses: characters in game, UTF-16 units on Discord.
     */
    public function countCharacters(string $content): int
    {
        return match ($this) {
            self::InGame => mb_strlen($content),
            self::Discord => CountDiscordCharacters::run($content),
        };
    }
}
