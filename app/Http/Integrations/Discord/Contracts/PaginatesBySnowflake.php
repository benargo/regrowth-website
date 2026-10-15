<?php

namespace App\Http\Integrations\Discord\Contracts;

use Saloon\PaginationPlugin\Contracts\Paginatable;

/**
 * A request whose results page by a snowflake cursor sent as `after` or `before`.
 */
interface PaginatesBySnowflake extends Paginatable
{
    /**
     * Dot path to each item's snowflake, e.g. `user.id` or `id`.
     */
    public function cursorKey(): string;

    /**
     * The query parameter the cursor is sent as: `after` or `before`.
     *
     * @return 'after'|'before'
     */
    public function cursorDirection(): string;
}
