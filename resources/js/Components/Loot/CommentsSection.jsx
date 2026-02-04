import { Link } from "@inertiajs/react";
import CommentForm from "./CommentForm";
import CommentItem from "./CommentItem";
import Icon from "@/Components/FontAwesome/Icon";

function Pagination({ links, meta }) {
    if (!links || meta.last_page <= 1) {
        return null;
    }

    return (
        <nav className="mt-6 flex items-center justify-between">
            <div className="text-sm text-gray-400">
                Showing {meta.from} to {meta.to} of {meta.total} comments
            </div>
            <div className="flex gap-1">
                {links.map((link, index) => {
                    if (!link.url) {
                        return (
                            <span
                                key={index}
                                className="rounded bg-gray-800 px-3 py-1 text-sm text-gray-500"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        );
                    }

                    return (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            className={`rounded px-3 py-1 text-sm transition-colors ${
                                link.active ? "bg-amber-600 text-white" : "bg-gray-700 text-gray-300 hover:bg-gray-600"
                            }`}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    );
                })}
            </div>
        </nav>
    );
}

export default function CommentsSection({ comments, itemId, canCreate }) {
    return (
        <section className="mt-12 w-full">
            <h2 className="mb-6 text-xl font-bold">Discussion</h2>

            {/* New comment form for raiders+ */}
            {canCreate ? (
                <div className="mb-8">
                    <CommentForm itemId={itemId} />
                </div>
            ) : (
                <div className="mb-8 rounded-lg border border-gray-700 bg-brown-800/50 p-4 italic text-gray-400">
                    <Icon icon="lock" style="solid" className="mr-2" />
                    {/* Only raiders may post comments. */}
                    Only officers may post comments. Raiders will be invited to make comments in the future.
                </div>
            )}

            {/* Comments list */}
            <div className="space-y-4">
                {comments.data.length > 0 ? (
                    <>
                        {comments.data.map((comment) => (
                            <CommentItem key={comment.id} comment={comment} itemId={itemId} />
                        ))}
                        <Pagination links={comments.links} meta={comments.meta} />
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
