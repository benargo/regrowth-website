import CommentForm from "./CommentForm";
import CommentItem from "./CommentItem";
import Icon from "@/Components/FontAwesome/Icon";
import Pagination from "@/Components/Pagination";

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
                <div className="mb-8 flex flex-row items-center rounded-lg border border-gray-700 bg-brown-800/50 p-4 italic text-gray-400">
                    <Icon icon="lock" style="solid" className="mr-2" />
                    <p>You do not have permission to post comments.</p>
                </div>
            )}

            {/* Comments list */}
            <div className="space-y-4">
                {comments.data.length > 0 ? (
                    <>
                        {comments.data.map((comment) => (
                            <CommentItem key={comment.id} comment={comment} itemId={itemId} />
                        ))}
                        <Pagination links={comments.links} meta={comments.meta} itemName="comments" />
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
