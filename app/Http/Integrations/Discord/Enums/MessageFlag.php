<?php

namespace App\Http\Integrations\Discord\Enums;

enum MessageFlag: int
{
    case Crossposted = 1 << 0;
    case IsCrosspost = 1 << 1;
    case SuppressEmbeds = 1 << 2;
    case SourceMessageDeleted = 1 << 3;
    case Urgent = 1 << 4;
    case HasThread = 1 << 5;
    case Ephemeral = 1 << 6;
    case Loading = 1 << 7;
    case FailedToMentionSomeRolesInThread = 1 << 8;
    case SuppressNotifications = 1 << 12;
    case IsVoiceMessage = 1 << 13;
    case HasSnapshot = 1 << 14;
    case IsComponentsV2 = 1 << 15;
}
