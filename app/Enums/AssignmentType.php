<?php

namespace App\Enums;

use App\Models\Character;
use App\Models\PlayableClass;
use App\Models\Spell;
use App\Models\TargetMarker;
use Illuminate\Database\Eloquent\Model;

enum AssignmentType: string
{
    case Character = 'character';
    case PlayableClass = 'playable_class';
    case Spell = 'spell';
    case TargetMarker = 'target_marker';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Character => Character::class,
            self::PlayableClass => PlayableClass::class,
            self::Spell => Spell::class,
            self::TargetMarker => TargetMarker::class,
        };
    }
}
