<?php

namespace App\Services\Discord\Enums;

enum PremiumType: int
{
    case NONE = 0;
    case NITRO_CLASSIC = 1;
    case NITRO = 2;
    case NITRO_BASIC = 3;
}
