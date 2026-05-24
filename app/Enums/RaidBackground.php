<?php

namespace App\Enums;

enum RaidBackground: string
{
    case KARAZHAN = 'bg-raid-karazhan';
    case GRUUL_MAGTHERIDON = 'bg-raid-gruul-magtheridon';
    case SERPENTSHRINE_CAVERN = 'bg-raid-serpentshrine-cavern';
    case TEMPEST_KEEP = 'bg-raid-tempest-keep';
    case SSC_TK = 'bg-ssctk';

    // Reserved for future use...
    // case MOUNT_HYJAL = 'bg-raid-mount-hyjal';
    // case BLACK_TEMPLE = 'bg-raid-black-temple';
    // case SUNWELL_PLATEAU = 'bg-raid-sunwell-plateau';
}
