<?php

namespace App\Actions\Comments;

use App\Models\Comment;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Resolves the root comment of the thread a reply belongs to.
 *
 * Returns null when no parent is given, the parent itself when it is a root,
 * or the parent's root when it is a reply. Aborts with a 404 when the parent
 * or its root is missing, soft-deleted, or belongs to a different commentable.
 */
class ResolveCommentThreadRoot
{
    use AsAction;

    public function handle(?int $parentId, Model $commentable): ?Comment
    {
        if ($parentId === null) {
            return null;
        }

        $parent = Comment::find($parentId);

        abort_if(
            $parent === null
                || $parent->commentable_type !== $commentable::class
                || $parent->commentable_id !== $commentable->getKey(),
            404,
        );

        if (! $parent->isReply()) {
            return $parent;
        }

        $root = $parent->parent;

        abort_if($root === null || $root->trashed(), 404);

        return $root;
    }
}
