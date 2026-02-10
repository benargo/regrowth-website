import { useState } from "react";
import { router } from "@inertiajs/react";
import Checkbox from "@/Components/Checkbox";
import CommentForm from "@/Components/Loot/CommentForm";
import FormattedMarkdown from "@/Components/FormattedMarkdown";
import Icon from "@/Components/FontAwesome/Icon";
import Pill from "@/Components/Pill";

export default function CommentItem({ comment, itemId }) {
    const [isEditing, setIsEditing] = useState(false);

    function handleDelete() {
        if (confirm("Are you sure you want to delete this comment?")) {
            router.delete(route("loot.items.comments.destroy", { item: itemId, comment: comment.id }), {
                preserveScroll: true,
            });
        }
    }

    function handleResolveToggle() {
        router.put(route("loot.items.comments.update", { item: itemId, comment: comment.id }), {
            preserveScroll: true,
            isResolved: !comment.is_resolved,
        });
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString("en-GB", {
            year: "numeric",
            month: "short",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });
    }

    return (
        <div className="rounded-lg border border-brown-700 bg-brown-800 p-4">
            {/* Header with user info and timestamp */}
            <div className="mb-3 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <img src={comment.user.avatar} alt={comment.user.display_name} className="h-8 w-8 rounded-full" />
                    <div>
                        <span className="font-medium">{comment.user.display_name}</span>
                        {comment.user.highest_role && (
                            <span className="mx-2">
                                <Pill
                                    bgColor={`bg-discord${comment.user.highest_role ? "-" + comment.user.highest_role.toLowerCase() : ""}`}
                                    className="ml-2"
                                >
                                    {comment.user.highest_role}
                                </Pill>
                            </span>
                        )}
                    </div>
                </div>
                <span className="text-sm text-gray-400">{formatDate(comment.created_at)}</span>
            </div>

            {/* Comment body or edit form */}
            {isEditing ? (
                <CommentForm
                    itemId={itemId}
                    commentId={comment.id}
                    initialBody={comment.body}
                    onSuccess={() => setIsEditing(false)}
                    onCancel={() => setIsEditing(false)}
                />
            ) : (
                <FormattedMarkdown>{comment.body}</FormattedMarkdown>
            )}

            {/* Actions */}
            {!isEditing && (comment.can.edit || comment.can.delete) && (
                <div className="mt-3 pt-3 flex gap-4 border-t border-brown-700 text-sm">
                    {comment.can.edit && (
                        <button
                            onClick={() => setIsEditing(true)}
                            className="text-amber-400 transition-colors hover:text-amber-300"
                        >
                            <Icon icon="edit" style="solid" className="mr-1" /> Edit
                        </button>
                    )}
                    {comment.can.delete && (
                        <button onClick={handleDelete} className="text-red-400 transition-colors hover:text-red-300">
                            <Icon icon="trash" style="solid" className="mr-1" /> Delete
                        </button>
                    )}
                    {comment.can.resolve && (
                        <span className={`flex items-center ${comment.is_resolved && "text-green-400 hover:text-green-600"} ml-auto`}>
                            <Checkbox
                                id={`resolve-${comment.id}`}
                                checked={comment.is_resolved}
                                onChange={handleResolveToggle}
                                className="checked:bg-green-400 hover:checked:bg-green-600 cursor-pointer"
                            />
                            <label 
                                htmlFor={`resolve-${comment.id}`}
                                className="pl-1 cursor-pointer select-none"
                            >
                                {comment.is_resolved ? "Resolved" : "Mark as resolved"}
                            </label>
                        </span>
                    )}
                    {!comment.can.resolve && comment.is_resolved && (
                        <span className="flex items-center text-green-400 ml-auto">
                            <Icon icon="check-circle" style="solid" className="mr-1" />
                            Resolved
                        </span>
                    )}
                </div>
            )}
        </div>
    );
}
