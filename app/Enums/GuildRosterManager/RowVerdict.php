<?php

namespace App\Enums\GuildRosterManager;

enum RowVerdict: string
{
    case NoOp = 'no_op';
    case Apply = 'apply';
    case Older = 'older';
    case Future = 'future';
    case Forbidden = 'forbidden';

    /**
     * Get the reason a row with this verdict is skipped, or null when the row is applied or unchanged.
     */
    public function skipReason(): ?SyncRowSkipReason
    {
        return match ($this) {
            self::Older => SyncRowSkipReason::Older,
            self::Future => SyncRowSkipReason::FutureTime,
            self::Forbidden => SyncRowSkipReason::Forbidden,
            self::NoOp, self::Apply => null,
        };
    }
}
