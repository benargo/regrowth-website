<?php

namespace App\Http\Requests\Concerns;

use App\Enums\RecruitmentMacroType;
use App\Rules\RecruitmentMacroContent;
use Illuminate\Validation\Rule;

/**
 * For form requests that save a recruitment macro: validates its title, type and content,
 * checking the content against the character limit of the submitted type.
 */
trait ValidatesRecruitmentMacroDetails
{
    /**
     * Get the validation rules for a recruitment macro's details.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function recruitmentMacroRules(): array
    {
        $contentRules = ['bail', 'required', 'string'];
        $type = $this->enum('type', RecruitmentMacroType::class);

        if ($type !== null) {
            $contentRules[] = new RecruitmentMacroContent($type);
        }

        return [
            'title' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(RecruitmentMacroType::class)],
            'content' => $contentRules,
        ];
    }
}
