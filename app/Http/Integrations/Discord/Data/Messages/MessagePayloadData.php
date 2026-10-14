<?php

namespace App\Http\Integrations\Discord\Data\Messages;

use Closure;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class MessagePayloadData extends Data
{
    public function __construct(
        /** @var string|Optional Message contents (up to 2000 characters) */
        #[StringType, Max(2000)]
        public readonly string|Optional $content,

        /** @var int|string|Optional Used to verify a message was sent (up to 25 characters) */
        public readonly int|string|Optional $nonce,

        /** @var bool|Optional True if this is a TTS message */
        #[BooleanType]
        public readonly bool|Optional $tts,

        /** @var array<EmbedData>|Optional Up to 10 rich embeds (up to 6000 characters) */
        #[DataCollectionOf(EmbedData::class)]
        public readonly array|Optional $embeds,

        /** @var MessageReferenceData|Optional Include to make your message a reply or a forward */
        public readonly MessageReferenceData|Optional $messageReference,

        /** @var array<string>|Optional IDs of up to 3 stickers in the server to send in the message (snowflakes) */
        #[Max(3)]
        public readonly array|Optional $stickerIds,

        /** @var string|Optional JSON-encoded body of non-file params, only for multipart/form-data requests */
        #[StringType]
        public readonly string|Optional $payloadJson,

        /** @var array<PartialAttachmentData>|Optional Partial attachment objects with filename and description */
        #[DataCollectionOf(PartialAttachmentData::class)]
        public readonly array|Optional $attachments,

        /** @var int|Optional Message flags combined as a bitfield */
        #[IntegerType, Min(0)]
        public readonly int|Optional $flags,

        /** @var bool|Optional If true and nonce is present, enforces uniqueness in the past few minutes */
        #[BooleanType]
        public readonly bool|Optional $enforceNonce,
    ) {}

    /**
     * Validate the nonce as an integer or a string of up to 25 characters, and cap the combined text of all embeds at 6000 characters.
     *
     * @return array<string, array<int, string|Closure>>
     */
    public static function rules(): array
    {
        return [
            'nonce' => ['sometimes', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_int($value) && ! is_string($value)) {
                    $fail('The :attribute must be an integer or a string.');

                    return;
                }

                if (is_string($value) && mb_strlen($value) > 25) {
                    $fail('The :attribute may not be greater than 25 characters.');
                }
            }],
            'embeds' => ['sometimes', 'array', 'max:10', function (string $attribute, mixed $value, Closure $fail): void {
                if (is_array($value) && self::embedCharacterCount($value) > 6000) {
                    $fail('The combined text of all embeds may not be greater than 6000 characters.');
                }
            }],
        ];
    }

    /**
     * Count the trimmed characters across every embed's title, description, field names and values, footer text and author name.
     *
     * @param  array<int, mixed>  $embeds
     */
    private static function embedCharacterCount(array $embeds): int
    {
        return collect($embeds)
            ->filter(fn (mixed $embed): bool => is_array($embed))
            ->flatMap(fn (array $embed): array => [
                data_get($embed, 'title'),
                data_get($embed, 'description'),
                data_get($embed, 'footer.text'),
                data_get($embed, 'author.name'),
                ...data_get($embed, 'fields.*.name', []),
                ...data_get($embed, 'fields.*.value', []),
            ])
            ->filter(fn (mixed $text): bool => is_string($text))
            ->sum(fn (string $text): int => mb_strlen(trim($text)));
    }
}
