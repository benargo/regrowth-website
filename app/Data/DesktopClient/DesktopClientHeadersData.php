<?php

namespace App\Data\DesktopClient;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * The desktop client a request names in its `X-Desktop-Client-Id` and `X-Desktop-Client-Name` headers.
 */
final class DesktopClientHeadersData extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}

    /**
     * Read the headers, or return null when the ID is not a UUID or the trimmed name is blank or over 100 characters.
     */
    public static function tryFromRequest(Request $request): ?self
    {
        $id = (string) $request->header('X-Desktop-Client-Id');
        $name = trim((string) $request->header('X-Desktop-Client-Name'));

        if (! Str::isUuid($id) || $name === '' || mb_strlen($name) > 100) {
            return null;
        }

        return new self($id, $name);
    }
}
