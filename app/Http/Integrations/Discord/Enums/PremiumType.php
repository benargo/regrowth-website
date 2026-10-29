<?php

namespace App\Http\Integrations\Discord\Enums;

enum PremiumType: int
{
    case None = 0;
    case NitroClassic = 1;
    case Nitro = 2;
    case NitroBasic = 3;
}
