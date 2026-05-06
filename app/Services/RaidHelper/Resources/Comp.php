<?php

namespace App\Services\RaidHelper\Resources;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\BuiltinTypeCast;
use Spatie\LaravelData\Data;

class Comp extends Data
{
    public function __construct(
        /** @var string The unique identifier of this comp */
        #[StringType]
        public readonly string $id,

        /** @var string The title of this comp */
        #[StringType]
        public readonly string $title,

        /** @var string Who can edit this comp (e.g. "managers" | "everyone") */
        #[StringType]
        public readonly string $editPermissions,

        /** @var bool Whether roles are shown */
        #[WithCast(BuiltinTypeCast::class, type: 'bool')]
        public readonly bool $showRoles,

        /** @var bool Whether classes are shown */
        #[WithCast(BuiltinTypeCast::class, type: 'bool')]
        public readonly bool $showClasses,

        /** @var int The number of groups in this comp */
        #[WithCast(BuiltinTypeCast::class, type: 'int')]
        public readonly int $groupCount,

        /** @var int The number of slots in this comp */
        #[WithCast(BuiltinTypeCast::class, type: 'int')]
        public readonly int $slotCount,

        /** @var array<int, CompGroup> The groups in this comp */
        #[DataCollectionOf(CompGroup::class)]
        public readonly array $groups,

        /** @var array<int, CompDivider> The dividers in this comp */
        #[DataCollectionOf(CompDivider::class)]
        public readonly array $dividers,

        /** @var array<int, EventClass> The classes in this comp */
        #[DataCollectionOf(EventClass::class)]
        public readonly array $classes,

        /** @var array<int, CompSlot> The slots in this comp */
        #[DataCollectionOf(CompSlot::class)]
        public readonly array $slots,
    ) {}
}
