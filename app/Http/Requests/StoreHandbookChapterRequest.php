<?php

namespace App\Http\Requests;

use App\Rules\ChapterContent;
use Illuminate\Foundation\Http\FormRequest;

class StoreHandbookChapterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => ['bail', 'required', 'string', new ChapterContent],
        ];
    }
}
