import { useState } from "react";
import { usePage } from "@inertiajs/react";
import { usePermission } from "@/Components/Authorizable";
import Checkbox from "@/Components/Checkbox";
import CommentForm from "@/Components/Comments/CommentForm";
import ConfirmationModal from "@/Components/ConfirmationModal";
import FormattedMarkdown from "@/Components/FormattedMarkdown";
import Icon from "@/Components/FontAwesome/Icon";
import Pill from "@/Components/Pill";
import Tooltip from "@/Components/Tooltip";

/**
 * Presentational comment row.
 *
 * Fires no HTTP requests of its own — every mutation is delegated to the
 * callbacks handed down by CommentsSection, which owns the list state.
 */
export default function CommentItem({ comment, onUpdate, onDelete, onAddReaction, onRemoveReaction }) {
    const { auth } = usePage().props;
    const [isEditing, setIsEditing] = useState(false);
    const [confirmingDelete, setConfirmingDelete] = useState(false);
    const canReactToComments = usePermission("react-to-comments");

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

    function ownReaction() {
        return comment.reactions.find((reaction) => reaction.user?.id === auth.user?.id) ?? null;
    }

    function userHasReacted() {
        return ownReaction() !== null;
    }

    function isOwnComment() {
        return comment.user?.id === auth.user?.id;
    }

    function handleDelete() {
        setConfirmingDelete(false);
        onDelete(comment.id);
    }

    function handleResolveToggle() {
        onUpdate(comment.id, { isResolved: !comment.is_resolved });
    }

    function handleReactionToggle() {
        const reaction = ownReaction();

        if (reaction) {
            onRemoveReaction(comment.id, reaction.id);
        } else {
            onAddReaction(comment.id);
        }
    }

    return (
        <>
            <div className="border-brown-700 bg-brown-800 rounded-lg border p-4">
                {/* Header with user info and timestamp */}
                <div className="mb-3 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <img
                            src={comment.user.avatar}
                            alt={comment.user.display_name}
                            className="h-8 w-8 rounded-full"
                        />
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
                        isEdit
                        initialBody={comment.body}
                        onSubmit={(body, callbacks) =>
                            onUpdate(comment.id, { body }, { ...callbacks, onSuccess: () => setIsEditing(false) })
                        }
                        onCancel={() => setIsEditing(false)}
                    />
                ) : (
                    <FormattedMarkdown>{comment.body}</FormattedMarkdown>
                )}

                {/* Actions */}
                {!isEditing && (
                    <div className="border-brown-700 mt-3 flex flex-col justify-start gap-4 border-t pt-3 text-sm md:flex-row">
                        {comment.can.edit && (
                            <button
                                onClick={() => setIsEditing(true)}
                                className="text-amber-400 transition-colors hover:text-amber-300"
                            >
                                <Icon icon="edit" style="solid" className="mr-1" /> Edit
                            </button>
                        )}
                        {comment.can.delete && (
                            <button
                                onClick={() => setConfirmingDelete(true)}
                                className="text-red-400 transition-colors hover:text-red-300"
                            >
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
                                    <label
                                        htmlFor={`resolve-${comment.id}`}
                                        className="cursor-pointer pl-1 select-none"
                                    >
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
                                    <Tooltip
                                        body={
                                            !canReactToComments
                                                ? "You do not have permission to react to comments."
                                                : isOwnComment()
                                                  ? "You may not react to your own comment."
                                                  : "You may not react to this comment."
                                        }
                                    >
                                        <button className="cursor-not-allowed text-gray-400" disabled>
                                            <Icon icon="thumbs-up" style="regular" />
                                        </button>
                                    </Tooltip>
                                )}
                                {comment.can.react && userHasReacted() && (
                                    <Tooltip body="Click to remove your reaction.">
                                        <button
                                            className="text-amber-400 transition-colors hover:text-amber-300"
                                            onClick={handleReactionToggle}
                                        >
                                            <Icon icon="thumbs-up" style="solid" />
                                        </button>
                                    </Tooltip>
                                )}
                                {comment.can.react && !userHasReacted() && (
                                    <Tooltip body="Click to like this comment.">
                                        <button
                                            className="text-white-400 transition-colors hover:text-gray-300"
                                            onClick={handleReactionToggle}
                                        >
                                            <Icon icon="thumbs-up" style="regular" />
                                        </button>
                                    </Tooltip>
                                )}
                                {comment.reactions.length > 0 && (
                                    <div className="ml-1 flex flex-row items-center">
                                        {comment.reactions.map((reaction, index) => (
                                            <Tooltip
                                                key={reaction.id}
                                                body={reaction.user?.display_name}
                                                style={{
                                                    marginLeft: index === 0 ? 0 : "-0.75rem",
                                                    zIndex: comment.reactions.length - index,
                                                }}
                                            >
                                                <img
                                                    src={reaction.user?.avatar}
                                                    alt={reaction.user?.display_name}
                                                    className="border-brown-800 h-6 w-6 rounded-full border-2"
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

            <ConfirmationModal
                show={confirmingDelete}
                onClose={() => setConfirmingDelete(false)}
                onConfirm={handleDelete}
                variant="delete"
                title="Delete comment?"
                confirmLabel="Delete"
            >
                Are you sure you want to delete this comment? This cannot be undone.
            </ConfirmationModal>
        </>
    );
}
