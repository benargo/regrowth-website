<?php

namespace App\Services\Discord\Resources;

use App\Services\Discord\Enums\EmbedType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class Embed extends Data
{
    public function __construct(
        /** @var string|null Title of embed */
        #[Nullable, StringType]
        public readonly ?string $title = null,

        /** @var EmbedType|null Type of embed */
        public readonly ?EmbedType $type = null,

        /** @var string|null Description of embed */
        #[Nullable, StringType]
        public readonly ?string $description = null,

        /** @var string|null URL of embed */
        #[Nullable, StringType]
        public readonly ?string $url = null,

        /** @var string|null ISO8601 timestamp of embed content */
        #[Nullable, StringType]
        public readonly ?string $timestamp = null,

        /** @var int|null Color code of the embed */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $color = null,

        /** @var EmbedFooter|null Footer information */
        public readonly ?EmbedFooter $footer = null,

        /** @var EmbedMedia|null Image information */
        public readonly ?EmbedMedia $image = null,

        /** @var EmbedMedia|null Thumbnail information */
        public readonly ?EmbedMedia $thumbnail = null,

        /** @var EmbedVideo|null Video information */
        public readonly ?EmbedVideo $video = null,

        /** @var EmbedProvider|null Provider information */
        public readonly ?EmbedProvider $provider = null,

        /** @var EmbedAuthor|null Author information */
        public readonly ?EmbedAuthor $author = null,

        /** @var array<EmbedField>|null Up to 25 field objects */
        #[DataCollectionOf(EmbedField::class)]
        public readonly ?array $fields = null,

        /** @var int|null Combined bitfield of embed flags */
        #[Nullable, IntegerType, Min(0)]
        public readonly ?int $flags = null,
    ) {}

    public static function rules(): array
    {
        return [
            'fields' => ['max:25'],
        ];
    }
}
