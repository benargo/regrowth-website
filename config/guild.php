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
    | - nationality:  ISO 3166-1 alpha-3, as supplied by the guild.
    | - country_code: ISO 3166-1 alpha-2, used to render the flag.
    | - demonym:      read out to screen readers in place of the bare code;
    |                 describes whichever flag is shown.
    | - tier:         council or officer. Entries are listed in display order.
    | - role:         council role line, hidden when empty; null for officers.
    | - image:        root-relative web path to a transparent WebP under
    |                 public/images/, cropped to the character's width. Every
    |                 image shares one height and baseline, so characters keep
    |                 their relative in-game size. Renders as a styled
    |                 silhouette if the file fails to load.
    | - width/height: the image's pixel dimensions.
    | - flag:         optional custom flag key (e.g. pride), shown instead of
    |                 the country_code flag.
    |
    */

    'officers' => [
        ['name' => 'Animorphus', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'British', 'tier' => 'council', 'role' => 'Guild historian', 'image' => '/images/officer_ani.webp', 'width' => 543, 'height' => 795],
        ['name' => 'Phoenyx', 'nationality' => 'BHR', 'country_code' => 'bh', 'demonym' => 'Bahraini', 'tier' => 'council', 'role' => 'Raid leader', 'image' => '/images/officer_pho.webp', 'width' => 190, 'height' => 795],
        ['name' => 'Soul', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'British', 'tier' => 'council', 'role' => 'Raid leader', 'image' => '/images/officer_soul.webp', 'width' => 233, 'height' => 795],
        ['name' => 'Zar', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'British', 'tier' => 'council', 'role' => 'Guild master', 'image' => '/images/officer_zar.webp', 'width' => 986, 'height' => 795],

        ['name' => 'Cal', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'British', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_cal.webp', 'width' => 343, 'height' => 795],
        ['name' => 'Cross', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'Out and proud', 'flag' => 'pride', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_cross.webp', 'width' => 423, 'height' => 795],
        ['name' => 'Dudum', 'nationality' => 'DNK', 'country_code' => 'dk', 'demonym' => 'Danish', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_dudum.webp', 'width' => 348, 'height' => 795],
        ['name' => 'Izepo', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'British', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_izepo.webp', 'width' => 561, 'height' => 795],
        ['name' => 'Mad', 'nationality' => 'BHR', 'country_code' => 'bh', 'demonym' => 'Bahraini', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_mad.webp', 'width' => 200, 'height' => 795],
        ['name' => 'Obscured', 'nationality' => 'NLD', 'country_code' => 'nl', 'demonym' => 'Dutch', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_obscured.webp', 'width' => 680, 'height' => 795],
        ['name' => 'Shiniko', 'nationality' => 'FRA', 'country_code' => 'fr', 'demonym' => 'French', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_shiniko.webp', 'width' => 429, 'height' => 795],
        ['name' => 'Waidelfi', 'nationality' => 'DEU', 'country_code' => 'de', 'demonym' => 'German', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_waid.webp', 'width' => 768, 'height' => 795],
    ],

];
