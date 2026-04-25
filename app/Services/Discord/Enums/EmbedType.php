<?php

namespace App\Services\Discord\Enums;

enum EmbedType: string
{
    case Rich = 'rich';
    case Image = 'image';
    case Video = 'video';
    case Gifv = 'gifv';
    case Article = 'article';
    case Link = 'link';
    case PollResult = 'poll_result';
}
