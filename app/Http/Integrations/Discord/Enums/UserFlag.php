<?php

namespace App\Http\Integrations\Discord\Enums;

enum UserFlag: int
{
    case Staff = 1 << 0;
    case Partner = 1 << 1;
    case Hypesquad = 1 << 2;
    case BugHunterLevel1 = 1 << 3;
    case HypesquadOnlineHouse1 = 1 << 6;
    case HypesquadOnlineHouse2 = 1 << 7;
    case HypesquadOnlineHouse3 = 1 << 8;
    case PremiumEarlySupporter = 1 << 9;
    case TeamPseudoUser = 1 << 10;
    case BugHunterLevel2 = 1 << 14;
    case VerifiedBot = 1 << 16;
    case VerifiedDeveloper = 1 << 17;
    case CertifiedModerator = 1 << 18;
    case BotHttpInteractions = 1 << 19;
}
