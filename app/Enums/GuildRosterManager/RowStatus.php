<?php

namespace App\Enums\GuildRosterManager;

enum RowStatus: string
{
    case Processed = 'processed';
    case Skipped = 'skipped';
    case Warning = 'warning';
    case Failed = 'failed';
}
