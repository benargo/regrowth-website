<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesRecruitmentMacroDetails;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecruitmentMacroRequest extends FormRequest
{
    use ValidatesRecruitmentMacroDetails;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->recruitmentMacroRules();
    }
}
