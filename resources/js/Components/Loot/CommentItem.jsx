import { useState } from "react";
import { useForm, router } from "@inertiajs/react";
import FormattedMarkdown from "@/Components/FormattedMarkdown";
import Icon from "@/Components/FontAwesome/Icon";
import InputError from "@/Components/InputError";
import Pill from "@/Components/Pill";

export default function CommentItem({ comment, itemId }) {
    const [isEditing, setIsEditing] = useState(false);
    const { data, setData, put, processing, errors, reset } = useForm({
        body: comment.body,
    });

    function submitEdit(e) {
        e.preventDefault();
        put(route("loot.items.comments.update", { item: itemId, comment: comment.id }), {
            preserveScroll: true,
            onSuccess: () => setIsEditing(false),
        });
    }

    function handleDelete() {
        if (confirm("Are you sure you want to delete this comment?")) {
            router.delete(route("loot.items.comments.destroy", { item: itemId, comment: comment.id }), {
                preserveScroll: true,
            });
        }
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
        <div className="rounded-lg border border-gray-700 bg-brown-800 p-4">
            {/* Header with user info and timestamp */}
            <div className="mb-3 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <img src={comment.user.avatar} alt={comment.user.display_name} className="h-8 w-8 rounded-full" />
                    <div>
                        <span className="font-medium">{comment.user.display_name}</span>
                        {comment.user.highest_role && (
                            <Pill
                                bgColor={`bg-discord${comment.user.highest_role ? "-" + comment.user.highest_role.toLowerCase() : ""}`}
                            >
                                {comment.user.highest_role}
                            </Pill>
                        )}
                    </div>
                </div>
                <span className="text-sm text-gray-400">{formatDate(comment.created_at)}</span>
            </div>

            {/* Comment body or edit form */}
            {isEditing ? (
                <form onSubmit={submitEdit}>
                    <textarea
                        value={data.body}
                        onChange={(e) => setData("body", e.target.value)}
                        rows={4}
                        className="w-full rounded-md border-gray-600 bg-brown-900 text-white focus:border-amber-500 focus:ring-amber-500"
                    />
                    <InputError message={errors.body} className="mt-2" />
                    <div className="mt-3 flex gap-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded bg-amber-600 px-3 py-1 text-sm font-medium transition-colors hover:bg-amber-700"
                        >
                            {processing ? "Saving..." : "Save"}
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setIsEditing(false);
                                reset();
                            }}
                            className="rounded bg-gray-600 px-3 py-1 text-sm font-medium transition-colors hover:bg-gray-700"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            ) : (
                <FormattedMarkdown>{comment.body}</FormattedMarkdown>
            )}

            {/* Actions */}
            {!isEditing && (comment.can.edit || comment.can.delete) && (
                <div className="mt-3 flex gap-4 border-t border-gray-700 pt-3 text-sm">
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
                </div>
            )}
        </div>
    );
}
