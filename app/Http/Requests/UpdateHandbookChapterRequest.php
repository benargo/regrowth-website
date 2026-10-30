<?php

namespace App\Http\Requests;

use App\Rules\ChapterContent;
use Illuminate\Foundation\Http\FormRequest;

class UpdateHandbookChapterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'content' => ['bail', 'required', 'string', ChapterContent::forSlug($this->route('chapter'))],
            'base_hash' => ['required', 'string', 'size:40'],
        ];
    }
}
