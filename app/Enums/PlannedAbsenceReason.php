<?php

namespace App\Enums;

enum PlannedAbsenceReason: string
{
    public const DEFAULT = self::Other;

    case Bereavement = 'Bereavement';
    case Caring = 'Caring for someone';
    case Education = 'School or education';
    case Event = 'At a concert';
    case Family = 'Family and social obligations';
    case Holiday = 'On holiday';
    case Medical = 'Medical recovery';
    case Religion = 'Religious observance';
    case Work = 'Stuck at work';
    case Other = 'Other';

    /**
     * Get the human-readable description of the absence reason.
     */
    public function description(): string
    {
        return match ($this) {
            self::Bereavement => 'Absence due to the loss of a loved one or pet. Take all the time you need.',
            self::Caring => 'Providing immediate care or assistance for a family member, child, partner, or pet.',
            self::Education => 'Exams, major study deadlines, evening classes, or school commitments.',
            self::Event => 'Attending concerts, gigs, festivals, live shows, or special performances.',
            self::Family => 'Family gatherings, birthdays, weddings, anniversaries, or pre-planned social obligations.',
            self::Holiday => 'Away on vacation, holiday trips, or long-distance travel.',
            self::Medical => 'Scheduled medical appointments, surgeries, acute illness, or health recovery time.',
            self::Religion => 'Observing religious holidays, cultural celebrations, services, or traditions.',
            self::Work => 'Stuck working late, unexpected overtime, business trips, or work schedule changes.',
            self::Other => 'A sensitive or unique reason that has been communicated directly to an officer.',
        };
    }

    /**
     * Get the FontAwesome icon name associated with the absence reason.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Bereavement => 'heart-broken',
            self::Caring => 'hand-holding-medical',
            self::Education => 'graduation-cap',
            self::Event => 'guitar-electric',
            self::Family => 'baby',
            self::Holiday => 'umbrella-beach',
            self::Medical => 'clinic-medical',
            self::Religion => 'praying-hands',
            self::Work => 'briefcase',
            self::Other => 'question',
        };
    }
}
