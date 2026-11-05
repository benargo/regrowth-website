<?php

namespace App\Contracts\RecruitmentMacros;

interface TrashesMacros
{
    /**
     * The number of days a trashed recruitment macro is kept before it is permanently deleted.
     */
    public const int TRASH_DAYS = 30;
}
