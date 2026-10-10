<?php

namespace App\Http\Integrations\Blizzard\Data\Account;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class WowAccountData extends Data
{
    /**
     * @param  array<int, AccountCharacterData>  $characters  Empty when the licence has no characters in the requested namespace.
     */
    public function __construct(
        public readonly int $id,
        #[DataCollectionOf(AccountCharacterData::class)]
        public readonly array $characters = [],
    ) {}
}
