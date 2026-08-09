import { useState } from "react";
import { Can } from "@/Components/Authorizable";
import { RotatingChevron } from "@/Components/Collapsible";
import CommentForm from "@/Components/Comments/CommentForm";
import CommentItem from "@/Components/Comments/CommentItem";
import Icon from "@/Components/FontAwesome/Icon";

/**
 * One root comment and its reply region.
 *
 * Presentational with respect to loading: the parent section owns the reply
 * cache, the partial reload, and the persisted expanded set. This component
 * decides only what the thread looks like in each of those states.
 */
export default function CommentThread({
    comment,
    replies = [],
    isExpanded = false,
    isLoadingReplies = false,
    replyError = null,
    hasMoreReplies = false,
    onToggle,
    onLoadMore,
    onReply,
    onUpdate,
    onDelete,
    onAddReaction,
    onRemoveReaction,
}) {
    const [replyTarget, setReplyTarget] = useState(null);
    const isReplying = replyTarget !== null;

    const regionId = `thread-${comment.id}`;
    const replyCount = comment.replies_count ?? 0;
    const isThreadLocked = comment.is_deleted;

    function handleReplySubmit(body, callbacks) {
        onReply(comment.id, body, {
            ...callbacks,
            onSuccess: () => {
                setReplyTarget(null);
                callbacks?.onSuccess?.();
            },
        });
    }

    function handleReplyClick(target) {
        setReplyTarget(target);

        if (!isExpanded) {
            onToggle(comment.id);
        }
    }

    return (
        <>
            <CommentItem
                comment={comment}
                onUpdate={onUpdate}
                onDelete={onDelete}
                onAddReaction={onAddReaction}
                onRemoveReaction={onRemoveReaction}
                onReply={() => handleReplyClick(comment)}
            />

            {(replyCount > 0 || isReplying) && (
                <div className="mt-2 ml-4 flex items-center gap-4 text-sm">
                    {replyCount > 0 && (
                        <button
                            type="button"
                            onClick={() => onToggle(comment.id)}
                            aria-expanded={isExpanded}
                            aria-controls={regionId}
                            className="flex items-center gap-2 text-amber-400 transition-colors hover:text-amber-300"
                        >
                            <RotatingChevron expanded={isExpanded} style="solid" />
                            {isExpanded ? "Hide replies" : replyCount === 1 ? "1 reply" : `${replyCount} replies`}
                        </button>
                    )}
                </div>
            )}

            {(isExpanded || isReplying) && (
                <div id={regionId} className="border-brown-700 mt-3 ml-4 space-y-3 border-l pl-4">
                    {isExpanded &&
                        replies.map((reply) => (
                            <CommentItem
                                key={reply.id}
                                comment={reply}
                                isReply
                                onUpdate={onUpdate}
                                onDelete={onDelete}
                                onAddReaction={onAddReaction}
                                onRemoveReaction={onRemoveReaction}
                                onReply={isThreadLocked ? null : () => handleReplyClick(reply)}
                            />
                        ))}

                    {isExpanded && isLoadingReplies && (
                        <div className="animate-pulse space-y-2" aria-hidden="true">
                            {[1, 2, 3].map((row) => (
                                <div key={row} className="h-12 rounded bg-gray-600/20" />
                            ))}
                        </div>
                    )}

                    {isExpanded && replyError && (
                        <p role="alert" className="text-sm text-red-400">
                            {replyError}
                        </p>
                    )}

                    {isExpanded && hasMoreReplies && !isLoadingReplies && (
                        <button
                            type="button"
                            onClick={() => onLoadMore(comment.id)}
                            className="text-sm text-amber-400 transition-colors hover:text-amber-300"
                        >
                            <Icon icon="arrow-down" style="solid" className="mr-1" /> Load 5 more replies
                        </button>
                    )}

                    {isReplying && (
                        <Can permission="comment-on-loot-items">
                            <CommentForm
                                key={replyTarget?.id}
                                onSubmit={handleReplySubmit}
                                onCancel={() => setReplyTarget(null)}
                                quotedComment={replyTarget?.parent_id ? replyTarget : null}
                                resetOnSuccess
                                isReply
                                autoFocus
                            />
                        </Can>
                    )}
                </div>
            )}
        </>
    );
}
