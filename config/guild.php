<?php

return [

    /*
    |--------------------------------------------------------------------------
    | World of Warcraft: Forever launch
    |--------------------------------------------------------------------------
    |
    | The homepage countdown target. Taken from Blizzard's own countdown_to
    | field on https://worldofwarcraft.blizzard.com/en-gb/forever. Stored as
    | an ISO 8601 string and sent to the client verbatim, so all countdown
    | arithmetic happens in the browser and a cached page can never render a
    | stale value.
    |
    */

    'forever_launch_at' => '2026-11-04T23:00:00Z',

    /*
    |--------------------------------------------------------------------------
    | Discord invite
    |--------------------------------------------------------------------------
    |
    | Single source of truth for the public invite link, shared by the nav,
    | the footer and the homepage call to action.
    |
    */

    'discord_invite_url' => env('DISCORD_INVITE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Officer team
    |--------------------------------------------------------------------------
    |
    | Displayed on the homepage. Static content rather than a database table:
    | it changes rarely and is not referenced anywhere else in the app.
    |
    | TEMPORARY: this belongs here only until officer management gets an
    | in-database solution (a GuildOfficer model / admin UI). Once that
    | lands, this array — and the config-based reads of it — should be
    | removed in favour of the database as the source of truth.
    |
    | - nationality:  ISO 3166-1 alpha-3, as supplied by the guild.
    | - country_code: ISO 3166-1 alpha-2, used to render the flag.
    | - demonym:      read out to screen readers in place of the bare code.
    |
    | Portrait art is optional. A profile with no image at
    | public/images/officers/<slug>.webp renders a styled silhouette instead.
    |
    */

    'officers' => [
        ['name' => 'Caldru', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'British'],
        ['name' => 'Fizzywigs', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'British'],
        ['name' => 'Loktorious', 'nationality' => 'ZAF', 'country_code' => 'za', 'demonym' => 'South African'],
        ['name' => 'Obscured', 'nationality' => 'NLD', 'country_code' => 'nl', 'demonym' => 'Dutch'],
        ['name' => 'Phoenyxbaka', 'nationality' => 'BHR', 'country_code' => 'bh', 'demonym' => 'Bahraini'],
        ['name' => 'Shiniko', 'nationality' => 'FRA', 'country_code' => 'fr', 'demonym' => 'French'],
        ['name' => 'Souljuice', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'British'],
        ['name' => 'Zargoat', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'British'],
    ],

];
