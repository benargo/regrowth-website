<?php

namespace App\Services\Discord\Enums;

enum MessageType: int
{
    case Default = 0;
    case RecipientAdd = 1;
    case RecipientRemove = 2;
    case Call = 3;
    case ChannelNameChange = 4;
    case ChannelIconChange = 5;
    case ChannelPinnedMessage = 6;
    case UserJoin = 7;
    case GuildBoost = 8;
    case GuildBoostTier1 = 9;
    case GuildBoostTier2 = 10;
    case GuildBoostTier3 = 11;
    case ChannelFollowAdd = 12;
    case GuildDiscoveryDisqualified = 14;
    case GuildDiscoveryRequalified = 15;
    case GuildDiscoveryGracePeriodInitialWarning = 16;
    case GuildDiscoveryGracePeriodFinalWarning = 17;
    case ThreadCreated = 18;
    case Reply = 19;
    case ChatInputCommand = 20;
    case ThreadStarterMessage = 21;
    case GuildInviteReminder = 22;
    case ContextMenuCommand = 23;
    case AutoModerationAction = 24;
    case RoleSubscriptionPurchase = 25;
    case InteractionPremiumUpsell = 26;
    case StageStart = 27;
    case StageEnd = 28;
    case StageSpeaker = 29;
    case StageTopic = 31;
    case GuildApplicationPremiumSubscription = 32;

    public function isDeletable(): bool
    {
        return match ($this) {
            self::RecipientAdd,
            self::RecipientRemove,
            self::Call,
            self::ChannelNameChange,
            self::ChannelIconChange,
            self::ThreadStarterMessage => false,
            default => true,
        };
    }
}
