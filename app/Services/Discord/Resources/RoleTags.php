<?php

namespace App\Services\Discord\Resources;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class RoleTags extends Data
{
    public function __construct(
        /** @var string|Optional The ID of the bot this role belongs to (snowflake) */
        #[Nullable, StringType]
        public readonly string|Optional $bot_id,

        /** @var string|Optional The ID of the integration this role belongs to (snowflake) */
        #[Nullable, StringType]
        public readonly string|Optional $integration_id,

        /** @var bool|Optional|null Whether this is the guild's Booster role (field present = true, absent = false) */
        public readonly bool|Optional|null $premium_subscriber,

        /** @var string|Optional The ID of this role's subscription SKU and listing (snowflake) */
        #[Nullable, StringType]
        public readonly string|Optional $subscription_listing_id,

        /** @var bool|Optional|null Whether this role is available for purchase (field present = true, absent = false) */
        public readonly bool|Optional|null $available_for_purchase,

        /** @var bool|Optional|null Whether this role is a guild's linked role (field present = true, absent = false) */
        public readonly bool|Optional|null $guild_connections,
    ) {}
}
