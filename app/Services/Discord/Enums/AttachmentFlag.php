<?php

namespace App\Services\Discord\Enums;

enum AttachmentFlag: int
{
    case IS_CLIP = 1 << 0; // this attachemnt is a Clip from a stream
    case IS_THUMBNAIL = 1 << 1; // this attachment is the thumbnail of a thread in a media channel, displayed in the grid but not on the message
    case IS_REMIX = 1 << 2; // this attachment has been edited using the remix feature on mobile (deprecated)
    case IS_SPOILER = 1 << 3; // this attachment was marked as a spoiler and is blurred until clicked
    case IS_ANIMATED = 1 << 4; // this attachment is an animated image
}
