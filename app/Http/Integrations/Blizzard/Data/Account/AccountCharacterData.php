<?php

namespace App\Http\Integrations\Blizzard\Data\Account;

use App\Http\Integrations\Blizzard\Data\Shared\HrefData;
use App\Http\Integrations\Blizzard\Data\Shared\LinkData;
use App\Http\Integrations\Blizzard\Data\Shared\RealmLinkData;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapInputName(SnakeCaseMapper::class)]
class AccountCharacterData extends Data
{
    /**
     * @param  array{type: string, name: string}  $gender
     * @param  array{type: string, name: string}  $faction
     */
    public function __construct(
        public readonly HrefData $character,
        public readonly HrefData $protectedCharacter,
        public readonly string $name,
        public readonly int $id,
        public readonly RealmLinkData $realm,
        public readonly LinkData $playableClass,
        public readonly LinkData $playableRace,
        public readonly array $gender,
        public readonly array $faction,
        public readonly int $level,
    ) {}
}
