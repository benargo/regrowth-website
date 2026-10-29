<?php

namespace App\Http\Integrations\Discord\Data\Messages;

use Closure;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;
use Spatie\LaravelData\Optional;

#[MapName(SnakeCaseMapper::class)]
final class PartialAttachmentData extends Data
{
    public function __construct(
        /** @var int|string Index of the uploaded file for new attachments, or the snowflake of an existing attachment */
        public readonly int|string $id,

        /** @var string|Optional Name of the file attached */
        #[StringType]
        public readonly string|Optional $filename,

        /** @var string|Optional Title of the file */
        #[StringType]
        public readonly string|Optional $title,

        /** @var string|Optional Description / alt-text for the file (max 1024 characters) */
        #[StringType, Max(1024)]
        public readonly string|Optional $description,

        /** @var float|Optional Duration in seconds of the audio or video file; required for voice messages */
        public readonly float|Optional $durationSecs,

        /** @var string|Optional Base64-encoded sampled waveform; required for voice messages */
        #[StringType]
        public readonly string|Optional $waveform,

        /** @var bool|Optional Whether to mark the attachment as a spoiler, setting the IS_SPOILER attachment flag */
        #[BooleanType]
        public readonly bool|Optional $isSpoiler,
    ) {}

    /**
     * Validate the id as either a file index or a snowflake string.
     *
     * @return array<string, array<int, string|Closure>>
     */
    public static function rules(): array
    {
        return [
            'id' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_int($value) && ! is_string($value)) {
                    $fail('The :attribute must be a file index or a snowflake.');
                }
            }],
        ];
    }
}
