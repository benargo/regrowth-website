<?php

namespace App\Http\Integrations\Blizzard\Data\Account;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * The `/profile/user/wow` response: one Battle.net account and its WoW licences.
 */
#[MapInputName(SnakeCaseMapper::class)]
class AccountProfileSummaryData extends Data
{
    /**
     * @param  int  $id  The Battle.net account id that owns the access token.
     * @param  array<int, WowAccountData>  $wowAccounts
     */
    public function __construct(
        public readonly int $id,
        #[DataCollectionOf(WowAccountData::class)]
        public readonly array $wowAccounts,
    ) {}
}
