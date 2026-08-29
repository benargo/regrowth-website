<?php

namespace App\Http\Integrations\RaidHelper\Data\Zones;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class ZoneData extends Data
{
    /** The line separating the human-readable description from the zone payload. */
    private const DESCRIPTION_MARKER = "-# Do not edit below this line...\n";

    public function __construct(
        /** @var int The local raids.id of this zone */
        #[IntegerType]
        public readonly int $id,

        /** @var string The name of this zone */
        #[StringType]
        public readonly string $name,

        /** @var ?int A sequencing hint for this zone within the event */
        #[Nullable, IntegerType]
        public readonly ?int $order = null,

        /**
         * The bosses selected for this zone.
         *
         * A null value means the key was absent, which signals that every boss
         * belonging to the zone should be taken. An empty array means the
         * payload explicitly selected none.
         *
         * @var ?array<int, ZoneBossData>
         */
        #[DataCollectionOf(ZoneBossData::class)]
        public readonly ?array $bosses = null,
    ) {}

    /**
     * Decode the zone payload embedded in a RaidHelper event description.
     *
     * The payload sits after a marker line that separates it from the prose
     * Discord shows to users. A description without the marker, without valid
     * JSON, or with no description at all yields an empty collection. Rows that
     * fail validation are logged and skipped so one bad zone cannot fail the
     * whole sync.
     *
     * @return Collection<int, static>
     */
    public static function collectFromDescription(?string $description): Collection
    {
        $payload = str($description)->after(self::DESCRIPTION_MARKER)->trim();

        if ($payload->exactly(str($description)->trim())) {
            return collect();
        }

        // Unescape before testing for JSON: RaidHelper may send the payload
        // slash-escaped, which isJson() would otherwise reject outright.
        $payload = $payload->pipe(stripslashes(...));

        if (! $payload->isJson()) {
            return collect();
        }

        return collect(json_decode($payload, true))
            ->map(function (mixed $row): ?static {
                try {
                    return static::validateAndCreate($row);
                } catch (ValidationException $exception) {
                    Log::error('ZoneData: skipping malformed zone row.', [
                        'row' => $row,
                        'errors' => $exception->errors(),
                    ]);

                    return null;
                }
            })
            ->filter()
            ->values();
    }
}
