<?php

namespace App\Services\Discord\Enums;

enum ChannelType: int
{
    case GUILD_TEXT = 0; // a text channel with a server
    case DM = 1; // a direct message between users
    case GUILD_VOICE = 2; // a voice channel with a server
    case GROUP_DM = 3; // a direct message between multiple users
    case GUILD_CATEGORY = 4; // an organizational category that contains up to 50 channels
    case GUILD_ANNOUNCEMENT = 5; // a channel that users can follow and crosspost into their own server (formerly news channels)
    case ANNOUNCEMENT_THREAD = 10; // a temporary sub-channel within a GUILD_ANNOUNCEMENT channel
    case PUBLIC_THREAD = 11; // a temporary sub-channel within a GUILD_TEXT or GUILD_FORUM channel
    case PRIVATE_THREAD = 12; // a temporary sub-channel within a GUILD_TEXT or GUILD_FORUM channel that is only viewable by those invited and those with the MANAGE_THREADS permission
    case GUILD_STAGE_VOICE = 13; // a voice channel for hosting events with an audience
    case GUILD_DIRECTORY = 14; // the channel in a hub containing the listed servers
    case GUILD_FORUM = 15; // a channel that can only contain threads and is displayed in a forum-like way
    case GUILD_MEDIA = 16; // a channel that can only contain threads, similar to GUILD_FORUM channels
}
