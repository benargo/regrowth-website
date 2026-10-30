<?php

namespace App\Data\Handbook;

use Spatie\LaravelData\Data;

final class ChapterHeadingData extends Data
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $emoji,
    ) {}
}
