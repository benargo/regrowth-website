<?php

namespace App\Contracts\Actions\Boss;

interface SharesRaidPageOrigins
{
    /**
     * The `from` value of a boss Create form opened from a raid's Edit page.
     */
    public const string FROM_RAID_EDIT = 'raid-edit';

    /**
     * The `from` value of a boss Create form opened from a raid's Bosses setup step.
     */
    public const string FROM_RAID_SETUP = 'raid-setup';

    /**
     * Every `from` value a boss Create form accepts.
     *
     * @var list<string>
     */
    public const array FROM_RAID_PAGES = [self::FROM_RAID_EDIT, self::FROM_RAID_SETUP];
}
