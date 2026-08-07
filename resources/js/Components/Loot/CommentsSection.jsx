import { useState, useEffect, useCallback, useMemo } from "react";
import { router, useHttp } from "@inertiajs/react";
import { useSocketId } from "@laravel/echo-react";
import CommentForm from "./CommentForm";
import CommentItem from "./CommentItem";
import Icon from "@/Components/FontAwesome/Icon";
import Pagination from "@/Components/Pagination";
import { Can, Cannot } from "@/Components/Authorizable";

/**
 * Merge incoming comment data over an existing comment while keeping the
 * viewer's own `can` flags.
 *
 * `CommentResource` computes `can.edit` / `can.delete` / `can.react` /
 * `can.resolve` for whoever made the request. On a broadcast that is the
 * *acting* user, not the recipient — trusting it would show every viewer the
 * author's Edit and Delete buttons. On a `useHttp` response it is the current
 * user and would be safe, but this merge is applied uniformly so there is only
 * one rule to remember.
 */
function mergePreservingCan(existing, incoming) {
    return { ...existing, ...incoming, can: existing.can };
}

export default function CommentsSection({ comments, itemId, registerBroadcastHandlers = null }) {
    const [items, setItems] = useState(comments.data);
    const [error, setError] = useState(null);
    const [newCommentCount, setNewCommentCount] = useState(0);

    const currentPage = comments.meta.current_page;

    const http = useHttp({});

    /**
     * Every mutation below broadcasts with `->toOthers()`. Laravel excludes
     * the triggering socket from that broadcast by reading `X-Socket-ID` off
     * the request, but `useHttp` (unlike a global Axios instance) does not
     * attach it automatically — without this the poster receives their own
     * broadcast back and, e.g., `createComment`'s optimistic prepend races
     * the broadcast-driven prepend, showing the comment twice until reload.
     */
    const socketId = useSocketId();
    const socketHeaders = useMemo(() => (socketId ? { "X-Socket-ID": socketId } : {}), [socketId]);

    // Resync when the server sends a new page of comments (pagination, or any
    // partial reload that includes the `comments` prop). Without this the local
    // copy would freeze at whatever it held on mount.
    useEffect(() => {
        setItems(comments.data);
    }, [comments]);

    const createComment = useCallback(
        (body, { onSuccess, onError }) => {
            setError(null);
            http.setData({
                commentable_type: "App\\Models\\Item",
                commentable_id: String(itemId),
                body,
            });
            http.post(route("api.comments.store"), {
                headers: socketHeaders,
                onSuccess: (response) => {
                    setItems((current) => {
                        if (current.some((comment) => comment.id === response.data.id)) {
                            return current;
                        }
                        return [response.data, ...current];
                    });
                    onSuccess?.();
                },
                onError: (errors) => onError?.(errors),
            });
        },
        [http, itemId, socketHeaders],
    );

    const updateComment = useCallback(
        (commentId, payload, { onSuccess, onError } = {}) => {
            setError(null);
            http.setData(payload);
            http.patch(route("api.comments.update", { comment: commentId }), {
                headers: socketHeaders,
                onSuccess: (response) => {
                    setItems((current) =>
                        current.map((comment) =>
                            comment.id === commentId ? mergePreservingCan(comment, response.data) : comment,
                        ),
                    );
                    onSuccess?.();
                },
                onError: (errors) => {
                    setError(errors?.body ?? "That comment could not be updated.");
                    onError?.(errors);
                },
            });
        },
        [http, socketHeaders],
    );

    const deleteComment = useCallback(
        (commentId) => {
            setError(null);
            http.setData({});
            http.delete(route("api.comments.destroy", { comment: commentId }), {
                headers: socketHeaders,
                onSuccess: () => {
                    setItems((current) => current.filter((comment) => comment.id !== commentId));
                },
                onHttpException: (httpResponse) => {
                    // 404 means it is already gone server-side — converge on that.
                    if (httpResponse.status === 404) {
                        setItems((current) => current.filter((comment) => comment.id !== commentId));
                        return false;
                    }
                    setError("That comment could not be deleted.");
                    return false;
                },
            });
        },
        [http, socketHeaders],
    );

    const addReaction = useCallback(
        (commentId) => {
            setError(null);
            http.setData({ comment_id: commentId });
            http.post(route("api.comments.reactions.store"), {
                headers: socketHeaders,
                onSuccess: (response) => {
                    setItems((current) =>
                        current.map((comment) =>
                            comment.id === commentId
                                ? { ...comment, reactions: [...comment.reactions, response.data] }
                                : comment,
                        ),
                    );
                },
                onError: () => setError("Your reaction could not be saved."),
            });
        },
        [http, socketHeaders],
    );

    const removeReaction = useCallback(
        (commentId, reactionId) => {
            setError(null);
            http.setData({});
            http.delete(route("api.comments.reactions.destroy", { reaction: reactionId }), {
                headers: socketHeaders,
                onSuccess: () => {
                    setItems((current) =>
                        current.map((comment) =>
                            comment.id === commentId
                                ? {
                                      ...comment,
                                      reactions: comment.reactions.filter((reaction) => reaction.id !== reactionId),
                                  }
                                : comment,
                        ),
                    );
                },
                onError: () => setError("Your reaction could not be removed."),
            });
        },
        [http, socketHeaders],
    );

    const goToPage = useCallback((page) => {
        router.reload({
            only: ["comments"],
            data: { page },
            preserveUrl: true,
            preserveScroll: true,
        });
    }, []);

    /**
     * Broadcast payloads carry `can` flags computed for the ACTING user, never
     * the viewer. A comment arriving from a broadcast is shown with every
     * permission denied; the viewer's real permissions are restored on the next
     * page load or pagination reload, which come from CommentResource rendered
     * for them.
     */
    const denyAllCan = { edit: false, delete: false, react: false, resolve: false };

    const handleCommentPosted = useCallback(
        (payload) => {
            const incoming = payload.comment;

            if (currentPage !== 1) {
                setNewCommentCount((count) => count + 1);
                return;
            }

            setItems((current) => {
                if (current.some((comment) => comment.id === incoming.id)) {
                    return current;
                }
                return [{ ...incoming, can: denyAllCan }, ...current];
            });
        },
        [currentPage],
    );

    const handleCommentChanged = useCallback((payload) => {
        const incoming = payload.comment;
        setItems((current) =>
            current.map((comment) => (comment.id === incoming.id ? mergePreservingCan(comment, incoming) : comment)),
        );
    }, []);

    const handleCommentRemoved = useCallback((payload) => {
        setItems((current) => current.filter((comment) => comment.id !== payload.comment_id));
    }, []);

    const handleCommentReactionChanged = useCallback((payload) => {
        setItems((current) =>
            current.map((comment) => {
                if (comment.id !== payload.comment_id) {
                    return comment;
                }

                if (payload.action === "created") {
                    if (comment.reactions.some((reaction) => reaction.id === payload.reaction.id)) {
                        return comment;
                    }
                    return { ...comment, reactions: [...comment.reactions, payload.reaction] };
                }

                return {
                    ...comment,
                    reactions: comment.reactions.filter((reaction) => reaction.id !== payload.reaction?.id),
                };
            }),
        );
    }, []);

    // Hand the broadcast handlers up to the page, which owns the item channel
    // subscription. Re-registers whenever a handler identity changes so the
    // page never holds a stale closure over `currentPage`.
    useEffect(() => {
        registerBroadcastHandlers?.({
            onCommentPosted: handleCommentPosted,
            onCommentChanged: handleCommentChanged,
            onCommentRemoved: handleCommentRemoved,
            onCommentReactionChanged: handleCommentReactionChanged,
        });
    }, [
        registerBroadcastHandlers,
        handleCommentPosted,
        handleCommentChanged,
        handleCommentRemoved,
        handleCommentReactionChanged,
    ]);

    return (
        <section className="mt-12 w-full">
            <h2 className="mb-6 text-xl font-bold">Discussion</h2>

            {/* New comment form for raiders+ */}
            <Can permission="comment-on-loot-items">
                <div className="mb-8">
                    <CommentForm onSubmit={createComment} resetOnSuccess />
                </div>
            </Can>
            <Cannot permission="comment-on-loot-items">
                <div className="bg-brown-800/50 mb-8 flex flex-row items-center rounded-lg border border-gray-700 p-4 text-gray-400 italic">
                    <Icon icon="lock" style="solid" className="mr-2" />
                    <p>You do not have permission to post comments.</p>
                </div>
            </Cannot>

            {newCommentCount > 0 && (
                <button
                    type="button"
                    onClick={() => {
                        setNewCommentCount(0);
                        goToPage(1);
                    }}
                    aria-live="polite"
                    className="border-primary bg-brown-800 hover:bg-brown-700 mb-4 inline-flex w-full items-center justify-center gap-2 rounded-md border px-4 py-2 text-sm text-amber-400 transition-colors"
                >
                    <Icon icon="arrow-up" style="solid" />
                    {newCommentCount === 1 ? "1 new comment" : `${newCommentCount} new comments`}
                    <span className="text-gray-400">— jump to the latest</span>
                </button>
            )}

            {error && (
                <p role="alert" className="mb-4 text-sm text-red-400">
                    {error}
                </p>
            )}

            {/* Comments list */}
            <div className="space-y-4">
                {items.length > 0 ? (
                    <>
                        {items.map((comment) => (
                            <CommentItem
                                key={comment.id}
                                comment={comment}
                                onUpdate={updateComment}
                                onDelete={deleteComment}
                                onAddReaction={addReaction}
                                onRemoveReaction={removeReaction}
                            />
                        ))}
                        <Pagination
                            links={comments.meta.links}
                            meta={comments.meta}
                            itemName="comments"
                            onPageChange={goToPage}
                        />
                    </>
                ) : (
                    <p className="py-8 text-center text-gray-400">
                        No comments yet. Be the first to share your thoughts!
                    </p>
                )}
            </div>
        </section>
    );
}
