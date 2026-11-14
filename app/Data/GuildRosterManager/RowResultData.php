<?php

namespace App\Data\GuildRosterManager;

use App\Enums\GuildRosterManager\RowReason;
use App\Enums\GuildRosterManager\RowStatus;
use Spatie\LaravelData\Data;

/**
 * The recorded outcome of processing one GRM row.
 */
final class RowResultData extends Data
{
    /**
     * @param  ?int  $characterId  The character the row was written to or matched, when there is one.
     * @param  string  $guid  The row's GRM GUID.
     * @param  string  $name  The row's GRM name.
     */
    public function __construct(
        public readonly ?int $characterId,
        public readonly string $guid,
        public readonly string $name,
        public readonly RowStatus $status,
        public readonly RowReason $reason,
        public readonly ?string $message = null,
    ) {}
}
