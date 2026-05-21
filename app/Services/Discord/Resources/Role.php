<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class Role extends Data
{
    public function __construct(
        /** @var string Role ID (snowflake) */
        #[StringType]
        public readonly string $id,

        /** @var string Role name */
        #[StringType]
        public readonly string $name,

        /** @var RoleColors The role's colors */
        public readonly RoleColors $colors,

        /** @var bool Whether the role appears pinned in the user listing */
        #[BooleanType]
        public readonly bool $hoist,

        /** @var int Position of this role (roles with the same position are sorted by ID) */
        #[IntegerType]
        public readonly int $position,

        /** @var string Permission bit set */
        #[StringType]
        public readonly string $permissions,

        /** @var bool Whether this role is managed by an integration */
        #[BooleanType]
        public readonly bool $managed,

        /** @var bool Whether this role is mentionable */
        #[BooleanType]
        public readonly bool $mentionable,

        /** @var int Role flags combined as a bitfield */
        #[IntegerType, Min(0)]
        public readonly int $flags,

        /** @var string|Optional Role icon hash */
        #[Nullable, StringType]
        public readonly string|Optional $icon,

        /** @var string|Optional|null Role unicode emoji */
        #[Nullable, StringType]
        public readonly string|Optional|null $unicode_emoji,

        /** @var RoleTags|Optional The tags this role has */
        public readonly RoleTags|Optional $tags,
    ) {}
}
