<?php

namespace App\Contracts\Actions\RaidInformation;

interface StoresRaidInformation
{
    public const DISK = 'raid-information';

    public const ORDER_FILE = 'chapters.json';

    public const TRASH_DIRECTORY = '.trashed';

    public const INTRODUCTION = 'introduction';

    public const CHARACTER_LIMIT = 2000;

    public const CACHE_TAG = 'raid-information';

    public const TTL_MINUTES = 10;

    public const TRASH_DAYS = 30;

    public const WRITE_LOCK = 'raid-information:write';

    public const SLUG_PATTERN = '/^[a-z0-9-]+$/';
}
