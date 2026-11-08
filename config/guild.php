<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Regrowth Loot Tool
    |--------------------------------------------------------------------------
    |
    | Download and source code links for the addon, shown on the loot tool
    | overview page.
    |
    */

    'loot-tool' => [
        'curseforge_url' => 'https://www.curseforge.com/wow/addons/regrowthloottool',
        'github_url' => 'https://github.com/regrowth-guild/loot-tool',
    ],

    /*
    |--------------------------------------------------------------------------
    | Discord chat roles
    |--------------------------------------------------------------------------
    |
    | The Discord roles that send a member to the Chat channel when they follow
    | a link to the Discord server: Classic Member, Anniversary Member and
    | Forever Member. Everyone else goes to the Introductions channel.
    |
    */

    'discord_chat_role_ids' => [
        '1549712849394335774',
        '1549712327497228338',
        '1548998062163107871',
    ],

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
        ['name' => 'Phoonix', 'nationality' => 'BHR', 'country_code' => 'bh', 'demonym' => 'Bahraini', 'tier' => 'council', 'role' => 'Raid leader', 'image' => '/images/officer_pho.webp', 'width' => 190, 'height' => 795],
        ['name' => 'Soul', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'British', 'tier' => 'council', 'role' => 'Raid leader', 'image' => '/images/officer_soul.webp', 'width' => 233, 'height' => 795],
        ['name' => 'Zar', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'British', 'tier' => 'council', 'role' => 'Guild master', 'image' => '/images/officer_zar.webp', 'width' => 645, 'height' => 795],

        ['name' => 'Cal', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'British', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_cal.webp', 'width' => 196, 'height' => 795],
        ['name' => 'Baka', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'Crippled', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_cross.webp', 'width' => 423, 'height' => 795],
        ['name' => 'Dudum', 'nationality' => 'DNK', 'country_code' => 'dk', 'demonym' => 'Danish', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_dudum.webp', 'width' => 348, 'height' => 795],
        ['name' => 'Izepo', 'nationality' => 'GBR', 'country_code' => 'gb', 'demonym' => 'British', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_izepo.webp', 'width' => 561, 'height' => 795],
        ['name' => 'Mad', 'nationality' => 'BHR', 'country_code' => 'bh', 'demonym' => 'Bahraini', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_mad.webp', 'width' => 200, 'height' => 795],
        ['name' => 'Obscured', 'nationality' => 'NLD', 'country_code' => 'nl', 'demonym' => 'Dutch', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_obscured.webp', 'width' => 680, 'height' => 795],
        ['name' => 'Shiniko', 'nationality' => 'FRA', 'country_code' => 'fr', 'demonym' => 'French', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_shiniko.webp', 'width' => 429, 'height' => 795],
        ['name' => 'Waidelfi', 'nationality' => 'AUT', 'country_code' => 'at', 'demonym' => 'Austrian', 'tier' => 'officer', 'role' => null, 'image' => '/images/officer_waid.webp', 'width' => 768, 'height' => 795],
    ],

];
