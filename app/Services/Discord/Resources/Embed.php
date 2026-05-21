<?php

namespace App\Services\Discord\Resources;

use App\Services\Discord\Enums\EmbedType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class Embed extends Data
{
    public function __construct(
        /** @var string|Optional Title of embed */
        #[Nullable, StringType]
        public readonly string|Optional $title,

        /** @var EmbedType|Optional Type of embed */
        public readonly EmbedType|Optional $type,

        /** @var string|Optional Description of embed */
        #[Nullable, StringType]
        public readonly string|Optional $description,

        /** @var string|Optional URL of embed */
        #[Nullable, StringType]
        public readonly string|Optional $url,

        /** @var string|Optional ISO8601 timestamp of embed content */
        #[Nullable, StringType]
        public readonly string|Optional $timestamp,

        /** @var int|Optional Color code of the embed */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $color,

        /** @var EmbedFooter|Optional|null Footer information */
        public readonly EmbedFooter|Optional|null $footer,

        /** @var EmbedMedia|Optional Image information */
        public readonly EmbedMedia|Optional $image,

        /** @var EmbedMedia|Optional Thumbnail information */
        public readonly EmbedMedia|Optional $thumbnail,

        /** @var EmbedVideo|Optional Video information */
        public readonly EmbedVideo|Optional $video,

        /** @var EmbedProvider|Optional Provider information */
        public readonly EmbedProvider|Optional $provider,

        /** @var EmbedAuthor|Optional Author information */
        public readonly EmbedAuthor|Optional $author,

        /** @var array<EmbedField>|Optional Up to 25 field objects */
        #[DataCollectionOf(EmbedField::class)]
        public readonly array|Optional $fields,

        /** @var int|Optional Combined bitfield of embed flags */
        #[Nullable, IntegerType, Min(0)]
        public readonly int|Optional $flags,
    ) {}

    public static function rules(): array
    {
        return [
            'fields' => ['max:25'],
        ];
    }
}
