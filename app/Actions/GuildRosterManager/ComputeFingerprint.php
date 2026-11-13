<?php

namespace App\Actions\GuildRosterManager;

use App\Contracts\Data\GuildRosterManager\Fingerprintable;
use Lorisleiva\Actions\Concerns\AsAction;

class ComputeFingerprint
{
    use AsAction;

    /**
     * Compute the lower-case hex SHA-256 of the row's canonical JSON: associative
     * keys sorted, lists kept in order, no whitespace, unescaped UTF-8 and slashes.
     */
    public function handle(Fingerprintable $row): string
    {
        $json = json_encode(
            $this->canonicalise($row->fingerprintPayload()),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );

        return hash('sha256', $json);
    }

    /**
     * Recursively sort the keys of every associative array.
     *
     * @param  array<int|string, mixed>  $value
     * @return array<int|string, mixed>
     */
    private function canonicalise(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalise($item);
            }
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return $value;
    }
}
