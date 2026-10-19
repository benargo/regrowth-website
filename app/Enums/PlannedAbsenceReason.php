<?php

namespace App\Enums;

enum PlannedAbsenceReason: string
{
    public const DEFAULT = self::Other;

    case Work = 'Stuck at work';
    case Holiday = 'On holiday';
    case Other = 'Other';

    /**
     * Get the human-readable description of the absence reason.
     */
    public function description(): string
    {
        return match ($this) {
            self::Holiday => 'Away on vacation, holiday trips, or long-distance travel.',
            self::Work => 'Stuck working late, unexpected overtime, business trips, or work schedule changes.',
            self::Other => 'Some other reason that is explained in the notes below.',
        };
    }

    /**
     * Get the FontAwesome icon name associated with the absence reason.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Holiday => 'umbrella-beach',
            self::Work => 'briefcase',
            self::Other => 'portal-exit',
        };
    }

    /**
     * Get the line shown in the main navigation when this absence covers the next raid.
     */
    public function navigationLabel(): string
    {
        return match ($this) {
            self::Holiday => 'You\'re on holiday',
            self::Work => 'You\'re stuck at work',
            self::Other => 'You\'re away',
        };
    }
}
