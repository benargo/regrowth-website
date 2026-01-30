import { Link } from '@inertiajs/react';
import CommentForm from './CommentForm';
import CommentItem from './CommentItem';

function Pagination({ links, meta }) {
    if (!links || meta.last_page <= 1) {
        return null;
    }

    return (
        <nav className="flex items-center justify-between mt-6">
            <div className="text-sm text-gray-400">
                Showing {meta.from} to {meta.to} of {meta.total} comments
            </div>
            <div className="flex gap-1">
                {links.map((link, index) => {
                    if (!link.url) {
                        return (
                            <span
                                key={index}
                                className="px-3 py-1 text-sm text-gray-500 bg-gray-800 rounded"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        );
                    }

                    return (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            className={`px-3 py-1 text-sm rounded transition-colors ${
                                link.active
                                    ? 'bg-amber-600 text-white'
                                    : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
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
        <section className="w-full mt-12">
            <h2 className="text-xl font-bold mb-6">Discussion</h2>

            {/* New comment form for raiders+ */}
            {canCreate ? (
                <div className="mb-8">
                    <CommentForm itemId={itemId} />
                </div>
            ) : (
                <div className="mb-8 text-gray-400 italic border border-gray-700 rounded-lg p-4 bg-brown-800/50">
                    <i className="fas fa-lock mr-2"></i>
                    {/* Only raiders may post comments. */}
                    Only officers may post comments. Raiders will be invited to make comments in the future.
                </div>
            )}

            {/* Comments list */}
            <div className="space-y-4">
                {comments.data.length > 0 ? (
                    <>
                        {comments.data.map(comment => (
                            <CommentItem key={comment.id} comment={comment} itemId={itemId} />
                        ))}
                        <Pagination links={comments.links} meta={comments.meta} />
                    </>
                ) : (
                    <p className="text-gray-400 text-center py-8">
                        No comments yet. Be the first to share your thoughts!
                    </p>
                )}
            </div>
        </section>
    );
}
