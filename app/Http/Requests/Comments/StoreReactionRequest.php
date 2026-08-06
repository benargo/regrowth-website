<?php

namespace App\Http\Requests\Comments;

use App\Models\Comment;
use App\Models\CommentReaction;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReactionRequest extends FormRequest
{
    private ?Comment $resolvedComment = null;

    /**
     * Determine if the user is authorized to make this request.
     *
     * The comment arrives in the body rather than the route, so the controller
     * cannot use #[Authorize('create', 'comment')] — that attribute resolves its
     * model from a route parameter. An absent or unknown `comment_id` is denied
     * here rather than thrown, so it surfaces as a 403 and not a 404 raised from
     * an authorization method.
     */
    public function authorize(): bool
    {
        $comment = $this->resolveComment();

        if ($comment === null) {
            return false;
        }

        return $this->user()->can('create', [CommentReaction::class, $comment]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comment_id' => ['required', 'integer', 'exists:comments,id'],
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
            'comment_id.required' => 'A comment must be specified.',
            'comment_id.exists' => 'The comment could not be found.',
        ];
    }

    /**
     * Get the comment being reacted to.
     */
    public function comment(): Comment
    {
        return $this->resolveComment() ?? Comment::findOrFail($this->input('comment_id'));
    }

    /**
     * Resolve the comment from the request body, memoising the lookup so
     * authorize() and the controller share one query.
     */
    private function resolveComment(): ?Comment
    {
        if ($this->resolvedComment !== null) {
            return $this->resolvedComment;
        }

        $commentId = $this->input('comment_id');

        if (! is_numeric($commentId)) {
            return null;
        }

        return $this->resolvedComment = Comment::find($commentId);
    }
}
