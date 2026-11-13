<?php

namespace App\Enums\GuildRosterManager;

enum UploadOutcome: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Processed = 'processed';
    case NoChanges = 'no_changes';
    case Superseded = 'superseded';
    case Failed = 'failed';
    case Staging = 'staging';
    case Abandoned = 'abandoned';
    case Expired = 'expired';
}
