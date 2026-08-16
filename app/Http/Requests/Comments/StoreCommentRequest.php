<?php

namespace App\Http\Requests\Comments;

use App\Models\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    /**
     * The commentable types a comment may be attached to.
     *
     * Free-form class names are not accepted: the payload would otherwise let a
     * caller name any Eloquent class, or one that does not exist.
     *
     * @var array<int, class-string<Model>>
     */
    public const ALLOWED_COMMENTABLE_TYPES = [
        Item::class,
    ];

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'commentable_type' => ['required', 'string', Rule::in(self::ALLOWED_COMMENTABLE_TYPES)],
            'commentable_id' => ['required', Rule::exists($this->commentableTable(), 'id')],
            'body' => ['required', 'string', 'min:3', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Please enter a comment.',
            'body.min' => 'Comment must be at least 3 characters.',
            'body.max' => 'Comment must not exceed 5000 characters.',
            'commentable_type.in' => 'Comments cannot be attached to that type of record.',
            'commentable_id.exists' => 'The record being commented on could not be found.',
            'parent_id.exists' => 'The comment being replied to could not be found.',
        ];
    }

    /**
     * Resolve the model the comment will be attached to.
     *
     * Only safe to call after validation, which guarantees the type is on the
     * allow-list and the id exists.
     */
    public function commentable(): Model
    {
        /** @var class-string<Model> $type */
        $type = $this->validated('commentable_type');

        return $type::findOrFail($this->validated('commentable_id'));
    }

    /**
     * Resolve the table the `commentable_id` exists rule should target.
     *
     * Runs before validation, so the submitted type may be absent or bogus; an
     * unresolvable type yields a table name that cannot match, and the
     * `commentable_type` rule reports the real error a moment later.
     */
    private function commentableTable(): string
    {
        $type = $this->input('commentable_type');

        if (! is_string($type) || ! in_array($type, self::ALLOWED_COMMENTABLE_TYPES, true)) {
            return 'comments';
        }

        /** @var class-string<Model> $type */
        return (new $type)->getTable();
    }
}
