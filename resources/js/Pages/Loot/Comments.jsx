import { useCallback, useEffect, useState } from "react";
import Master from "@/Layouts/Master";
import { Link, router, usePage } from "@inertiajs/react";
import CommentThread from "@/Components/Comments/CommentThread";
import Icon from "@/Components/FontAwesome/Icon";
import Pagination from "@/Components/Pagination";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import ItemIcon from "@/Components/Items/ItemIcon";
import ToolNav from "@/Components/ToolNav";
import useExpandedThreads from "@/Hooks/useExpandedThreads";

export default function Comments({ comments, replies }) {
    const { auth } = usePage().props;
    const { isExpanded, toggle } = useExpandedThreads(auth.user?.id);
    const [threadCache, setThreadCache] = useState({});
    const [loadingRoots, setLoadingRoots] = useState([]);

    // Seed every thread from the eager first page that ships with `comments`.
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

    // Append each "load more" response; offsets only move forward.
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

    const loadMoreReplies = useCallback(
        (rootId) => {
            setLoadingRoots([rootId]);

            router.reload({
                only: ["replies"],
                data: { offsets: { [rootId]: threadCache[rootId]?.loadedCount ?? 0 } },
                preserveUrl: true,
                preserveScroll: true,
                onError: () => setLoadingRoots([]),
            });
        },
        [threadCache],
    );

    // Group comments by item on the client side
    const groupedComments = comments.data.reduce((groups, comment) => {
        const itemId = comment.commentable?.id ?? comment.commentable;
        if (!groups[itemId]) {
            groups[itemId] = {
                item: comment.commentable,
                comments: [],
            };
        }
        groups[itemId].comments.push(comment);
        return groups;
    }, {});

    return (
        <Master title="All Comments">
            <SharedHeader backgroundClass="bg-ssctk" title="Loot Bias" />

            <ToolNav>
                <Link
                    href={route("loot.index")}
                    className="hover:border-primary hover:bg-brown-800 active:border-primary my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white"
                >
                    <Icon icon="arrow-left" style="solid" className="mr-2" />
                    <span>Back to loot biases</span>
                </Link>
            </ToolNav>

            {/* Content */}
            <PageContainer>
                <h2 className="mb-6 text-xl font-bold">All Comments</h2>

                {comments.data.length > 0 ? (
                    <>
                        <div className="space-y-8">
                            {Object.entries(groupedComments).map(([itemId, group]) => (
                                <section key={itemId}>
                                    <div className="relative mb-4">
                                        <Link
                                            href={route("loot.items.show", {
                                                item: group.item.id,
                                                slug: group.item.slug,
                                            })}
                                            className="flex items-center gap-3 transition-colors hover:text-amber-300"
                                        >
                                            {group.item?.icon && <div className="h-8 w-8 flex-none" />}
                                            <h3 className="text-lg font-semibold text-amber-400 hover:text-amber-300">
                                                {group.item?.name ?? `Item #${itemId}`}
                                            </h3>
                                        </Link>
                                        {group.item?.icon && (
                                            <div className="absolute top-1/2 left-0 -translate-y-1/2">
                                                <ItemIcon
                                                    itemId={group.item.id}
                                                    itemName={group.item.name}
                                                    itemQuality={group.item.quality_border_class}
                                                    iconUrl={group.item.icon}
                                                    size={8}
                                                />
                                            </div>
                                        )}
                                    </div>
                                    <div className="space-y-4">
                                        {group.comments.map((comment) => {
                                            const thread = threadCache[comment.id] ?? {
                                                replies: [],
                                                loadedCount: 0,
                                            };

                                            return (
                                                <CommentThread
                                                    key={comment.id}
                                                    comment={comment}
                                                    replies={thread.replies}
                                                    isExpanded={isExpanded(comment.id)}
                                                    isLoadingReplies={loadingRoots.includes(comment.id)}
                                                    hasMoreReplies={(comment.replies_count ?? 0) > thread.loadedCount}
                                                    onToggle={toggle}
                                                    onLoadMore={loadMoreReplies}
                                                />
                                            );
                                        })}
                                    </div>
                                </section>
                            ))}
                        </div>
                        <Pagination links={comments.meta.links} meta={comments.meta} itemName="comments" />
                    </>
                ) : (
                    <p className="py-8 text-center text-gray-400">No comments yet.</p>
                )}
            </PageContainer>
        </Master>
    );
}
