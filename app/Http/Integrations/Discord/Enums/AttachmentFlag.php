<?php

namespace App\Http\Integrations\Discord\Enums;

enum AttachmentFlag: int
{
    /** This attachment is a Clip from a stream. */
    case IsClip = 1 << 0;

    /** This attachment is the thumbnail of a thread in a media channel, displayed in the grid but not on the message. */
    case IsThumbnail = 1 << 1;

    /** This attachment has been edited using the remix feature on mobile (deprecated). */
    case IsRemix = 1 << 2;

    /** This attachment was marked as a spoiler and is blurred until clicked. */
    case IsSpoiler = 1 << 3;

    /** This attachment is an animated image. */
    case IsAnimated = 1 << 5;
}
