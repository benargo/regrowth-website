import { useState, useEffect, useCallback, useMemo } from "react";
import { router, useHttp, usePage } from "@inertiajs/react";
import { useSocketId } from "@laravel/echo-react";
import CommentForm from "./CommentForm";
import CommentThread from "./CommentThread";
import Icon from "@/Components/FontAwesome/Icon";
import Pagination from "@/Components/Pagination";
import { Can, Cannot } from "@/Components/Authorizable";
import useExpandedThreads from "@/Hooks/useExpandedThreads";

/**
 * Merge incoming comment data over an existing comment while keeping the
 * viewer's own `permissions` flags.
 *
 * `CommentResource` computes `permissions.edit` / `permissions.delete` /
 * `permissions.react` / `permissions.resolve` for whoever made the request.
 * On a broadcast that is the *acting* user, not the recipient — trusting it
 * would show every viewer the author's Edit and Delete buttons. On a
 * `useHttp` response it is the current user and would be safe, but this
 * merge is applied uniformly so there is only one rule to remember.
 */
function mergePreservingPermissions(existing, incoming) {
    return { ...existing, ...incoming, permissions: existing.permissions };
}

export default function CommentsSection({ comments, replies, itemId, registerBroadcastHandlers = null }) {
    const [items, setItems] = useState(comments.data);
    const [error, setError] = useState(null);
    const [newCommentCount, setNewCommentCount] = useState(0);
    const [threadCache, setThreadCache] = useState({});
    const [loadingRoots, setLoadingRoots] = useState([]);

    const currentPage = comments.meta.current_page;

    const { auth } = usePage().props;
    const { isExpanded, expand, toggle } = useExpandedThreads(auth.user?.id);

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

    /**
     * Seed each thread from the `comments` prop's eager first page of replies.
     *
     * This runs on every `comments` reload, including pagination, so replies
     * pulled in by "load more" are deliberately dropped: a fresh page load
     * always starts a thread back at its first page, matching the decision not
     * to persist reply position.
     */
    useEffect(() => {
        setThreadCache(
            Object.fromEntries(
                comments.data.map((comment) => [
                    comment.id,
                    { replies: comment.replies ?? [], loadedCount: (comment.replies ?? []).length },
                ]),
            ),
        );
    }, [comments]);

    /**
     * Merge a "load more" response by appending to each root's cached list.
     *
     * Offsets only ever move forward within a page view, so there is no
     * overlap to reconcile — this is always an append, never a replace.
     */
    useEffect(() => {
        if (!replies) {
            return;
        }

        setThreadCache((current) => {
            const next = { ...current };

            Object.entries(replies).forEach(([rootId, incoming]) => {
                const existing = next[rootId] ?? { replies: [], loadedCount: 0 };
                const known = new Set(existing.replies.map((reply) => reply.id));
                const added = incoming.filter((reply) => !known.has(reply.id));

                next[rootId] = {
                    replies: [...existing.replies, ...added],
                    loadedCount: existing.loadedCount + added.length,
                };
            });

            return next;
        });

        setLoadingRoots([]);
    }, [replies]);

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

    /**
     * Expanding never fetches: the first page of every thread already shipped
     * with `comments`, so the toggle only reveals what is cached. A thread with
     * more replies than are loaded shows its "Load 5 more replies" control.
     */
    const toggleThread = useCallback((rootId) => toggle(rootId), [toggle]);

    const loadMoreReplies = useCallback(
        (rootId) => {
            const rootIds = Array.isArray(rootId) ? rootId : [rootId];

            setLoadingRoots(rootIds);

            const offsets = Object.fromEntries(rootIds.map((id) => [id, threadCache[id]?.loadedCount ?? 0]));

            router.reload({
                only: ["replies"],
                data: { offsets },
                preserveUrl: true,
                preserveScroll: true,
                onError: () => {
                    setLoadingRoots([]);
                    setError("Those replies could not be loaded.");
                },
            });
        },
        [threadCache],
    );

    const createReply = useCallback(
        (rootId, body, { onSuccess, onError } = {}) => {
            setError(null);
            http.setData({
                commentable_type: "App\\Models\\Item",
                commentable_id: String(itemId),
                body,
                parent_id: rootId,
            });
            http.post(route("api.comments.store"), {
                headers: socketHeaders,
                onSuccess: (response) => {
                    const reply = response.data;

                    setThreadCache((current) => {
                        const existing = current[reply.parent_id] ?? { replies: [], loadedCount: 0 };

                        if (existing.replies.some((cached) => cached.id === reply.id)) {
                            return current;
                        }

                        return {
                            ...current,
                            [reply.parent_id]: {
                                replies: [...existing.replies, reply],
                                loadedCount: existing.loadedCount + 1,
                            },
                        };
                    });

                    setItems((current) =>
                        current.map((comment) =>
                            comment.id === reply.parent_id
                                ? { ...comment, replies_count: (comment.replies_count ?? 0) + 1 }
                                : comment,
                        ),
                    );

                    // Posting a reply expands that thread for the poster, and
                    // persists it — only for them.
                    expand(reply.parent_id);

                    onSuccess?.();
                },
                onError: (errors) => {
                    setError(errors?.body ?? "Your reply could not be posted.");
                    onError?.(errors);
                },
            });
        },
        [http, itemId, socketHeaders, expand],
    );

    /**
     * Broadcast payloads carry `permissions` flags computed for the ACTING
     * user, never the viewer. A comment arriving from a broadcast is shown
     * with every permission denied; the viewer's real permissions are
     * restored on the next page load or pagination reload, which come from
     * CommentResource rendered for them. Also used to mask a deleted comment
     * in place, where no action should be offered.
     */
    const denyAllPermissions = { edit: false, delete: false, react: false, resolve: false, reply: false };

    /**
     * Apply a transform to a comment wherever it lives — the top-level list or
     * any thread's cached replies.
     */
    const updateCommentEverywhere = useCallback((commentId, transform) => {
        setItems((current) => current.map((comment) => (comment.id === commentId ? transform(comment) : comment)));

        setThreadCache((current) => {
            const next = {};

            Object.entries(current).forEach(([rootId, thread]) => {
                next[rootId] = {
                    ...thread,
                    replies: thread.replies.map((reply) => (reply.id === commentId ? transform(reply) : reply)),
                };
            });

            return next;
        });
    }, []);

    const updateComment = useCallback(
        (commentId, payload, { onSuccess, onError } = {}) => {
            setError(null);
            http.setData(payload);
            http.patch(route("api.comments.update", { comment: commentId }), {
                headers: socketHeaders,
                onSuccess: (response) => {
                    updateCommentEverywhere(response.data.id, (comment) =>
                        mergePreservingPermissions(comment, response.data),
                    );
                    onSuccess?.();
                },
                onError: (errors) => {
                    setError(errors?.body ?? "That comment could not be updated.");
                    onError?.(errors);
                },
            });
        },
        [http, socketHeaders, updateCommentEverywhere],
    );

    /**
     * Remove a comment from local state — a root becomes a tombstone in place
     * (its replies survive it), a reply is dropped from its thread's cache.
     */
    const removeCommentLocally = useCallback(
        (commentId) => {
            setItems((current) =>
                current.map((comment) =>
                    comment.id === commentId
                        ? { ...comment, is_deleted: true, body: null, permissions: denyAllPermissions }
                        : comment,
                ),
            );

            setThreadCache((current) => {
                const next = {};

                Object.entries(current).forEach(([rootId, thread]) => {
                    const remaining = thread.replies.filter((reply) => reply.id !== commentId);

                    next[rootId] = {
                        replies: remaining,
                        loadedCount: Math.max(0, thread.loadedCount - (thread.replies.length - remaining.length)),
                    };
                });

                return next;
            });

            setItems((current) =>
                current.map((comment) => {
                    const thread = threadCache[comment.id];
                    const wasReply = thread?.replies.some((reply) => reply.id === commentId);

                    return wasReply
                        ? { ...comment, replies_count: Math.max(0, (comment.replies_count ?? 0) - 1) }
                        : comment;
                }),
            );
        },
        [threadCache],
    );

    const deleteComment = useCallback(
        (commentId) => {
            setError(null);
            http.setData({});
            http.delete(route("api.comments.destroy", { comment: commentId }), {
                headers: socketHeaders,
                onSuccess: () => {
                    removeCommentLocally(commentId);
                },
                onHttpException: (httpResponse) => {
                    // 404 means it is already gone server-side — converge on that.
                    if (httpResponse.status === 404) {
                        removeCommentLocally(commentId);
                        return false;
                    }
                    setError("That comment could not be deleted.");
                    return false;
                },
            });
        },
        [http, socketHeaders, removeCommentLocally],
    );

    const addReaction = useCallback(
        (commentId) => {
            setError(null);
            http.setData({ comment_id: commentId });
            http.post(route("api.comments.reactions.store"), {
                headers: socketHeaders,
                onSuccess: (response) => {
                    updateCommentEverywhere(commentId, (comment) => ({
                        ...comment,
                        reactions: [...comment.reactions, response.data],
                    }));
                },
                onError: () => setError("Your reaction could not be saved."),
            });
        },
        [http, socketHeaders, updateCommentEverywhere],
    );

    const removeReaction = useCallback(
        (commentId, reactionId) => {
            setError(null);
            http.setData({});
            http.delete(route("api.comments.reactions.destroy", { reaction: reactionId }), {
                headers: socketHeaders,
                onSuccess: () => {
                    updateCommentEverywhere(commentId, (comment) => ({
                        ...comment,
                        reactions: comment.reactions.filter((reaction) => reaction.id !== reactionId),
                    }));
                },
                onError: () => setError("Your reaction could not be removed."),
            });
        },
        [http, socketHeaders, updateCommentEverywhere],
    );

    const goToPage = useCallback((page) => {
        router.reload({
            only: ["comments"],
            data: { page },
            preserveUrl: true,
            preserveScroll: true,
        });
    }, []);

    const handleCommentPosted = useCallback(
        (payload) => {
            const incoming = payload.comment;

            // A reply lands in its thread, never at the top of the list.
            if (payload.parent_id) {
                setItems((current) =>
                    current.map((comment) =>
                        comment.id === payload.parent_id
                            ? { ...comment, replies_count: (comment.replies_count ?? 0) + 1 }
                            : comment,
                    ),
                );

                setThreadCache((current) => {
                    const thread = current[payload.parent_id];

                    // Only append into a thread that is already fully loaded up
                    // to its count; otherwise the reply arrives out of order and
                    // the bumped count alone tells the user there is more.
                    if (!thread || thread.replies.some((reply) => reply.id === incoming.id)) {
                        return current;
                    }

                    return {
                        ...current,
                        [payload.parent_id]: {
                            replies: [...thread.replies, { ...incoming, permissions: denyAllPermissions }],
                            loadedCount: thread.loadedCount + 1,
                        },
                    };
                });

                return;
            }

            if (currentPage !== 1) {
                setNewCommentCount((count) => count + 1);
                return;
            }

            setItems((current) => {
                if (current.some((comment) => comment.id === incoming.id)) {
                    return current;
                }
                return [{ ...incoming, permissions: denyAllPermissions }, ...current];
            });
        },
        [currentPage],
    );

    const handleCommentChanged = useCallback(
        (payload) => {
            updateCommentEverywhere(payload.comment.id, (comment) =>
                mergePreservingPermissions(comment, payload.comment),
            );
        },
        [updateCommentEverywhere],
    );

    const handleCommentRemoved = useCallback((payload) => {
        // A removed root becomes a tombstone rather than vanishing — its
        // replies survive it and still need somewhere to hang.
        if (payload.is_root) {
            setItems((current) =>
                current.map((comment) =>
                    comment.id === payload.comment_id
                        ? { ...comment, is_deleted: true, body: null, permissions: denyAllPermissions }
                        : comment,
                ),
            );
            return;
        }

        setItems((current) =>
            current.map((comment) =>
                comment.id === payload.parent_id
                    ? { ...comment, replies_count: Math.max(0, (comment.replies_count ?? 0) - 1) }
                    : comment,
            ),
        );

        setThreadCache((current) => {
            const thread = current[payload.parent_id];

            if (!thread) {
                return current;
            }

            const remaining = thread.replies.filter((reply) => reply.id !== payload.comment_id);

            return {
                ...current,
                [payload.parent_id]: {
                    replies: remaining,
                    loadedCount: Math.max(0, thread.loadedCount - (thread.replies.length - remaining.length)),
                },
            };
        });
    }, []);

    const handleCommentReactionChanged = useCallback(
        (payload) => {
            updateCommentEverywhere(payload.comment_id, (comment) => {
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
            });
        },
        [updateCommentEverywhere],
    );

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
                        {items.map((comment) => {
                            const thread = threadCache[comment.id] ?? { replies: [], loadedCount: 0 };

                            return (
                                <CommentThread
                                    key={comment.id}
                                    comment={comment}
                                    replies={thread.replies}
                                    isExpanded={isExpanded(comment.id)}
                                    isLoadingReplies={loadingRoots.includes(comment.id)}
                                    hasMoreReplies={(comment.replies_count ?? 0) > thread.loadedCount}
                                    onToggle={toggleThread}
                                    onLoadMore={loadMoreReplies}
                                    onReply={createReply}
                                    onUpdate={updateComment}
                                    onDelete={deleteComment}
                                    onAddReaction={addReaction}
                                    onRemoveReaction={removeReaction}
                                />
                            );
                        })}
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
