<?php

namespace App\Rules;

use App\Contracts\Actions\RaidInformation\StoresRaidInformation;
use Closure;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use League\Flysystem\UnableToReadFile;

/**
 * Validates that a hash matches the stored content of a live chapter.
 */
class MatchesChapterHash implements StoresRaidInformation, ValidationRule
{
    public function __construct(
        #[Storage(self::DISK)] protected Filesystem $disk,
        protected string $slug,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $current = $this->disk->get("{$this->slug}.md");
        } catch (UnableToReadFile) {
            $current = null;
        }

        if ($current === null || sha1($current) !== $value) {
            $fail('Someone saved this chapter after you opened it. Their version is shown below; merge in anything you need, then save again.');
        }
    }
}
