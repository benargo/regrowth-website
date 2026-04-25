<?php

namespace App\Services\Discord\Enums;

enum MessageReferenceType: int
{
    /** A standard reference used by replies. */
    case Default = 0;

    /** Reference used to point to a message at a point in time (forwards). */
    case Forward = 1;
}
