<?php

namespace App\Rules;

use App\Actions\RaidInformation\CountDiscordCharacters;
use App\Contracts\Actions\RaidInformation\StoresRaidInformation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates chapter content: within the Discord character limit and opening with the correct heading.
 */
class ChapterContent implements StoresRaidInformation, ValidationRule
{
    public function __construct(public readonly bool $isIntroduction = false) {}

    /**
     * Create the rule for the chapter with the given slug.
     */
    public static function forSlug(string $slug): self
    {
        return new self($slug === self::INTRODUCTION);
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $content = (string) $value;
        $count = CountDiscordCharacters::run($content);

        if ($count > self::CHARACTER_LIMIT) {
            $fail(sprintf('The chapter is %s characters long; Discord allows %s.', number_format($count), number_format(self::CHARACTER_LIMIT)));
        }

        $prefix = $this->isIntroduction ? '# ' : '## ';
        $firstLine = preg_split('/\R/u', $content, 2)[0];

        if (preg_match('/^'.preg_quote($prefix, '/').'\S/u', $firstLine) !== 1) {
            $fail("The first line must be a heading starting with \"{$prefix}\".");
        }
    }
}
