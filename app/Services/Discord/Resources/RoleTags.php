<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class RoleTags extends Data
{
    public function __construct(
        /** @var string|null The ID of the bot this role belongs to (snowflake) */
        #[Nullable, StringType]
        public readonly ?string $bot_id = null,

        /** @var string|null The ID of the integration this role belongs to (snowflake) */
        #[Nullable, StringType]
        public readonly ?string $integration_id = null,

        /** @var bool Whether this is the guild's Booster role (field present = true, absent = false) */
        public readonly bool $premium_subscriber = false,

        /** @var string|null The ID of this role's subscription SKU and listing (snowflake) */
        #[Nullable, StringType]
        public readonly ?string $subscription_listing_id = null,

        /** @var bool Whether this role is available for purchase (field present = true, absent = false) */
        public readonly bool $available_for_purchase = false,

        /** @var bool Whether this role is a guild's linked role (field present = true, absent = false) */
        public readonly bool $guild_connections = false,
    ) {}
}
