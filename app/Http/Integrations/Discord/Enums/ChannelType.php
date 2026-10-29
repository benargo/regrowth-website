<?php

namespace App\Http\Integrations\Discord\Enums;

enum ChannelType: int
{
    /** A text channel within a server. */
    case GuildText = 0;

    /** A direct message between users. */
    case Dm = 1;

    /** A voice channel within a server. */
    case GuildVoice = 2;

    /** A direct message between multiple users. */
    case GroupDm = 3;

    /** An organizational category that contains up to 50 channels. */
    case GuildCategory = 4;

    /** A channel that users can follow and crosspost into their own server (formerly news channels). */
    case GuildAnnouncement = 5;

    /** A temporary sub-channel within a GUILD_ANNOUNCEMENT channel. */
    case AnnouncementThread = 10;

    /** A temporary sub-channel within a GUILD_TEXT or GUILD_FORUM channel. */
    case PublicThread = 11;

    /** A temporary sub-channel within a GUILD_TEXT or GUILD_FORUM channel, only viewable by those invited and those with the MANAGE_THREADS permission. */
    case PrivateThread = 12;

    /** A voice channel for hosting events with an audience. */
    case GuildStageVoice = 13;

    /** The channel in a hub containing the listed servers. */
    case GuildDirectory = 14;

    /** A channel that can only contain threads and is displayed in a forum-like way. */
    case GuildForum = 15;

    /** A channel that can only contain threads, similar to GUILD_FORUM channels. */
    case GuildMedia = 16;

    /**
     * The thread channel types.
     *
     * @return list<self>
     */
    public static function threadTypes(): array
    {
        return [self::AnnouncementThread, self::PublicThread, self::PrivateThread];
    }
}
