<?php

namespace App\Http\Integrations\Discord\Data\Guilds;

use App\Http\Integrations\Discord\Data\Users\UserData;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\ArrayType;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class EmojiData extends Data
{
    public function __construct(
        /** @var string Emoji ID (snowflake) */
        #[StringType]
        public readonly string $id,

        /** @var string Emoji name */
        #[StringType]
        public readonly string $name,

        /** @var array<int, string>|Optional IDs of the roles allowed to use this emoji */
        #[ArrayType]
        public readonly array|Optional $roles,

        /** @var UserData|Optional The user that created this emoji */
        public readonly UserData|Optional $user,

        /** @var bool|Optional Whether this emoji must be wrapped in colons */
        #[BooleanType]
        public readonly bool|Optional $requireColons,

        /** @var bool|Optional Whether this emoji is managed */
        #[BooleanType]
        public readonly bool|Optional $managed,

        /** @var bool|Optional Whether this emoji is animated */
        #[BooleanType]
        public readonly bool|Optional $animated,

        /** @var bool|Optional Whether this emoji can be used */
        #[BooleanType]
        public readonly bool|Optional $available,
    ) {}
}
