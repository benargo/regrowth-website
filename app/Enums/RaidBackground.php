<?php

namespace App\Enums;

enum RaidBackground: string
{
    // Individual raids
    case Karazhan = 'bg-raid-karazhan';
    case Gruul = 'bg-raid-gruul';
    case Magtheridon = 'bg-raid-magtheridon';
    case SerpentshrineCavern = 'bg-raid-serpentshrine-cavern';
    case TempestKeep = 'bg-raid-tempest-keep';
    case HyjalSummit = 'bg-raid-hyjal-summit';
    case BlackTemple = 'bg-raid-black-temple';
    case ZulAman = 'bg-raid-zulaman';
    case SunwellPlateau = 'bg-raid-sunwell-plateau';

    // Joint raids
    case GruulAndMagtheridon = 'bg-raid-gruul-magtheridon';
    case SerpentshrineCavernAndTempestKeep = 'bg-ssctk';
}
