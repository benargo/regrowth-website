<?php

namespace App\Contracts\Actions\Handbook;

interface StoresHandbook
{
    /**
     * The filesystem disk holding the chapter files.
     */
    public const string DISK = 'handbook';

    /**
     * The file on the disk holding the ordered list of chapter slugs.
     */
    public const string ORDER_FILE = 'chapters.json';

    /**
     * The directory on the disk holding trashed chapter files.
     */
    public const string TRASH_DIRECTORY = '.trashed';

    /**
     * The slug of the chapter that always comes first and can't be deleted.
     */
    public const string INTRODUCTION = 'introduction';

    /**
     * The maximum length of a chapter, in Discord characters.
     */
    public const int CHARACTER_LIMIT = 2000;

    /**
     * The cache tag holding the chapter list.
     */
    public const string CACHE_TAG = 'handbook';

    /**
     * The number of minutes the chapter list stays cached.
     */
    public const int TTL_MINUTES = 10;

    /**
     * The number of days a trashed chapter is kept before it is permanently deleted.
     */
    public const int TRASH_DAYS = 30;

    /**
     * The cache lock held while chapter files or the order file are written.
     */
    public const string WRITE_LOCK = 'handbook:write';

    /**
     * The pattern a chapter slug must match.
     */
    public const string SLUG_PATTERN = '/^[a-z0-9-]+$/';
}
