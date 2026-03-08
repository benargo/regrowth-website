<?php

namespace App\Traits;

trait ParsesFilterParam
{
    /**
     * Parse a filter param string into an array of integers, or null for "no filter".
     *
     * - 'none'       → [] (filter to nothing, returns zero rows)
     * - 'all' or absent → null (no restriction; caller applies its own defaults)
     * - '1,2,3'      → [1, 2, 3]
     */
    protected function parseFilterParam(string $key): ?array
    {
        if (! $this->has($key)) {
            return null;
        }

        $value = $this->input($key);

        if ($value === 'none') {
            return [];
        }

        if ($value === null || $value === 'all') {
            return null;
        }

        return array_map('intval', explode(',', $value));
    }
}
