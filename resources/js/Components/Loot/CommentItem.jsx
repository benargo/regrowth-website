import { useState } from "react";
import { router, usePage } from "@inertiajs/react";
import Checkbox from "@/Components/Checkbox";
import CommentForm from "@/Components/Loot/CommentForm";
import FormattedMarkdown from "@/Components/FormattedMarkdown";
import Icon from "@/Components/FontAwesome/Icon";
import Pill from "@/Components/Pill";
import Tooltip from "@/Components/Tooltip";

export default function CommentItem({ comment, itemId }) {
    const { auth } = usePage().props;
    const [isEditing, setIsEditing] = useState(false);

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

    function handleDelete() {
        if (confirm("Are you sure you want to delete this comment?")) {
            router.delete(route("loot.comments.destroy", { item: itemId, comment: comment.id }), {
                preserveScroll: true,
            });
        }
    }

    function handleResolveToggle() {
        router.put(
            route("loot.comments.update", { item: itemId, comment: comment.id }),
            {
                isResolved: !comment.is_resolved,
            },
            {
                preserveScroll: true,
            },
        );
    }

    function handleReactionToggle() {
        if (userHasReacted()) {
            // Find the reaction ID for the current user's reaction
            const reaction = comment.reactions.find((reaction) => reaction.user?.id === auth.user?.id);
            if (reaction) {
                router.delete(
                    route("loot.comments.reactions.destroy", { comment: comment.id, reaction: reaction.id }),
                    {
                        preserveScroll: true,
                    },
                );
            }
        } else {
            router.post(
                route("loot.comments.reactions.store", { comment: comment.id }),
                {},
                {
                    preserveScroll: true,
                },
            );
        }
    }

    function userHasReacted() {
        return comment.reactions.some((reaction) => reaction.user?.id === auth.user?.id) || false;
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
            {!isEditing && (
                <div className="mt-3 flex flex-col justify-start gap-4 border-t border-brown-700 pt-3 text-sm md:flex-row">
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
                    <div className="flex flex-col items-center gap-4 md:ml-auto md:flex-row">
                        {comment.can.resolve && (
                            <span
                                className={`flex items-center ${comment.is_resolved && "text-green-400 hover:text-green-600"}`}
                            >
                                <Checkbox
                                    id={`resolve-${comment.id}`}
                                    checked={comment.is_resolved}
                                    onChange={handleResolveToggle}
                                    className="cursor-pointer checked:bg-green-400 hover:checked:bg-green-600"
                                />
                                <label htmlFor={`resolve-${comment.id}`} className="cursor-pointer select-none pl-1">
                                    {comment.is_resolved ? "Resolved" : "Mark as resolved"}
                                </label>
                            </span>
                        )}
                        {!comment.can.resolve && comment.is_resolved && (
                            <span className="flex items-center text-green-400">
                                <Icon icon="check-circle" style="solid" className="mr-1" />
                                Resolved
                            </span>
                        )}
                        <div className="flex flex-row items-center gap-1">
                            {!comment.can.react && (
                                <Tooltip text="You may not like your own comment.">
                                    <button className="cursor-not-allowed text-gray-400" disabled>
                                        <Icon icon="thumbs-up" style="regular" />
                                    </button>
                                </Tooltip>
                            )}
                            {comment.can.react && userHasReacted() && (
                                <Tooltip text="Click to remove your reaction.">
                                    <button
                                        className="text-amber-400 transition-colors hover:text-amber-300"
                                        onClick={handleReactionToggle}
                                    >
                                        <Icon icon="thumbs-up" style="solid" />
                                    </button>
                                </Tooltip>
                            )}
                            {comment.can.react && !userHasReacted() && (
                                <Tooltip text="Click to like this comment.">
                                    <button
                                        className="text-white-400 transition-colors hover:text-gray-300"
                                        onClick={handleReactionToggle}
                                    >
                                        <Icon icon="thumbs-up" style="regular" />
                                    </button>
                                </Tooltip>
                            )}
                            {comment.reactions.length > 0 && (
                                    <p className="text-sm text-gray-400">
                                        {comment.reactions.length}
                                        {comment.reactions.length === 0 && <span className="sr-only"> reactions</span>}
                                    </p>
                                ) && (
                                    <div className="ml-1 flex flex-row items-center">
                                        {comment.reactions.map((reaction, index) => (
                                            <Tooltip
                                                key={reaction.id}
                                                text={reaction.user?.display_name}
                                                style={{
                                                    marginLeft: index === 0 ? 0 : "-0.75rem",
                                                    zIndex: comment.reactions.length - index,
                                                }}
                                            >
                                                <img
                                                    src={reaction.user?.avatar}
                                                    alt={reaction.user?.display_name}
                                                    className="h-6 w-6 rounded-full border-2 border-brown-800"
                                                />
                                            </Tooltip>
                                        ))}
                                    </div>
                                )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
