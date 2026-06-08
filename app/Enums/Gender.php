<?php

namespace App\Enums;

enum Gender: string
{
    case FEMALE = 'Female';
    case MALE = 'Male';

    /**
     * @see https://community.developer.battle.net/documentation/world-of-warcraft/guides/character-renders
     */
    public static function fromId(int $id): self
    {
        return match ($id) {
            0 => self::MALE,
            1 => self::FEMALE,
        };
    }

    /**
     * @see https://community.developer.battle.net/documentation/world-of-warcraft/guides/character-renders
     */
    public function id(): int
    {
        return match ($this) {
            self::MALE => 0,
            self::FEMALE => 1
        };
    }
}
