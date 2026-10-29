<?php

namespace App\Enums\GuildRosterManager;

enum UploadStatus: string
{
    case Current = 'current';
    case Outdated = 'outdated';
    case Stale = 'stale';
    case Missing = 'missing';
    case Unknown = 'unknown';
}
