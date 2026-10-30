<?php

namespace App\Rules;

use App\Actions\Handbook\ParseChapterHeading;
use App\Contracts\Actions\Handbook\StoresHandbook;
use Closure;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that chapter content's heading yields a slug that is not empty, reserved, in use or in Recently deleted.
 */
class AvailableChapterHeading implements StoresHandbook, ValidationRule
{
    public function __construct(
        #[Storage(self::DISK)] protected Filesystem $disk,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $slug = Str::slug(ParseChapterHeading::run((string) $value)->title);

        if ($slug === '') {
            $fail('The heading needs some words to make the chapter’s address from.');

            return;
        }

        if ($slug === self::INTRODUCTION) {
            $fail('“introduction” is reserved. Change the heading.');

            return;
        }

        if ($this->disk->exists("{$slug}.md") || $this->disk->exists(self::TRASH_DIRECTORY."/{$slug}.md")) {
            $fail("A chapter at “{$slug}” already exists or is in Recently deleted. Change the heading.");
        }
    }
}
