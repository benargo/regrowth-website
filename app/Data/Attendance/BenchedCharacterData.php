<?php

namespace App\Data\Attendance;

use App\Data\Transformers\ToResourceTransformer;
use App\Models\Character;
use App\Models\CharacterReport;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

/**
 * A character benched in a report, labelled with the report's guild tag name.
 */
final class BenchedCharacterData extends Data
{
    public function __construct(
        public readonly string $tag,
        #[WithTransformer(ToResourceTransformer::class)]
        public readonly Character $character,
    ) {}

    /**
     * An untagged report's tag is "Untagged".
     */
    public static function fromCharacterReport(CharacterReport $characterReport): self
    {
        return new self(
            tag: $characterReport->report->guildTag?->name ?? 'Untagged',
            character: $characterReport->character,
        );
    }
}
