<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'blizzard' => [
        'client_id' => env('BLIZZARD_CLIENT_ID'),
        'client_secret' => env('BLIZZARD_CLIENT_SECRET'),
        'region' => 'eu',
        'locale' => 'en_GB',
        'filesystem' => env('BLIZZARD_FILESYSTEM', 'public'),
    ],

    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI', '/auth/discord/callback'),
        'token' => env('DISCORD_BOT_TOKEN'),
        'guild_id' => 829020506907869214,
        'channels' => [
            'announcements' => env('DISCORD_CHANNEL_ANNOUNCEMENTS'),
            'lootcouncil' => env('DISCORD_CHANNEL_LOOTCOUNCIL'),
            'officer' => env('DISCORD_CHANNEL_OFFICER'),
        ],
    ],

    'warcraftlogs' => [
        'client_id' => env('WCL_CLIENT_ID'),
        'client_secret' => env('WCL_CLIENT_SECRET'),
        'token_url' => 'https://www.warcraftlogs.com/oauth/token',
        'graphql_url' => 'https://www.warcraftlogs.com/api/v2/client',
        'guild_id' => 774848,
    ],

];
