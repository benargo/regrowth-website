<?php

namespace App\Rules;

use App\Enums\RecruitmentMacroType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Validates that macro content is within the character limit of its type.
 */
class RecruitmentMacroContent implements ValidationRule
{
    public function __construct(public readonly RecruitmentMacroType $type) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $count = $this->type->countCharacters((string) $value);
        $limit = $this->type->characterLimit();

        if ($count > $limit) {
            $fail(sprintf('The macro is %s characters long; %s allows %s.', number_format($count), $this->type->label(), number_format($limit)));
        }
    }
}
