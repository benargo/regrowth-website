<?php

namespace App\Http\Integrations\Discord\Data\Guilds;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class RoleTagsData extends Data
{
    public function __construct(
        /** @var string|Optional The ID of the bot this role belongs to (snowflake) */
        #[StringType]
        public readonly string|Optional $botId,

        /** @var string|Optional The ID of the integration this role belongs to (snowflake) */
        #[StringType]
        public readonly string|Optional $integrationId,

        /** @var bool|Optional|null Whether this is the guild's Booster role (field present = true, absent = false) */
        public readonly bool|Optional|null $premiumSubscriber,

        /** @var string|Optional The ID of this role's subscription SKU and listing (snowflake) */
        #[StringType]
        public readonly string|Optional $subscriptionListingId,

        /** @var bool|Optional|null Whether this role is available for purchase (field present = true, absent = false) */
        public readonly bool|Optional|null $availableForPurchase,

        /** @var bool|Optional|null Whether this role is a guild's linked role (field present = true, absent = false) */
        public readonly bool|Optional|null $guildConnections,
    ) {}
}
