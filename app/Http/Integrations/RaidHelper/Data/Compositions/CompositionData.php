<?php

namespace App\Http\Integrations\RaidHelper\Data\Compositions;

use App\Http\Integrations\RaidHelper\Data\Events\EventClassData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\BuiltinTypeCast;
use Spatie\LaravelData\Data;

class CompositionData extends Data
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

        /** @var array<int, CompositionGroupData> The groups in this comp */
        #[DataCollectionOf(CompositionGroupData::class)]
        public readonly array $groups,

        /** @var array<int, CompositionDividerData> The dividers in this comp */
        #[DataCollectionOf(CompositionDividerData::class)]
        public readonly array $dividers,

        /** @var array<int, EventClassData> The classes in this comp */
        #[DataCollectionOf(EventClassData::class)]
        public readonly array $classes,

        /** @var array<int, CompositionSlotData> The slots in this comp */
        #[DataCollectionOf(CompositionSlotData::class)]
        public readonly array $slots,
    ) {}
}
