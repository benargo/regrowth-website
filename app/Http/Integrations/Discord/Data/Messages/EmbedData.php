<?php

namespace App\Http\Integrations\Discord\Data\Messages;

use App\Http\Integrations\Discord\Enums\EmbedType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class EmbedData extends Data
{
    public function __construct(
        /** @var string|Optional Title of embed (max 256 characters) */
        #[StringType, Max(256)]
        public readonly string|Optional $title,

        /** @var EmbedType|Optional Type of embed */
        public readonly EmbedType|Optional $type,

        /** @var string|Optional Description of embed (max 4096 characters) */
        #[StringType, Max(4096)]
        public readonly string|Optional $description,

        /** @var string|Optional URL of embed */
        #[StringType]
        public readonly string|Optional $url,

        /** @var string|Optional ISO8601 timestamp of embed content */
        #[StringType]
        public readonly string|Optional $timestamp,

        /** @var int|Optional Color code of the embed */
        #[IntegerType, Min(0)]
        public readonly int|Optional $color,

        /** @var EmbedFooterData|Optional Footer information */
        public readonly EmbedFooterData|Optional $footer,

        /** @var EmbedMediaData|Optional Image information */
        public readonly EmbedMediaData|Optional $image,

        /** @var EmbedMediaData|Optional Thumbnail information */
        public readonly EmbedMediaData|Optional $thumbnail,

        /** @var EmbedVideoData|Optional Video information */
        public readonly EmbedVideoData|Optional $video,

        /** @var EmbedProviderData|Optional Provider information */
        public readonly EmbedProviderData|Optional $provider,

        /** @var EmbedAuthorData|Optional Author information */
        public readonly EmbedAuthorData|Optional $author,

        /** @var array<EmbedFieldData>|Optional Up to 25 field objects */
        #[DataCollectionOf(EmbedFieldData::class), Max(25)]
        public readonly array|Optional $fields,

        /** @var int|Optional Combined bitfield of embed flags */
        #[IntegerType, Min(0)]
        public readonly int|Optional $flags,
    ) {}
}
