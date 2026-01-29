import { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import FormattedMarkdown from '@/Components/FormattedMarkdown';
import InputError from '@/Components/InputError';

export default function CommentItem({ comment, itemId }) {
    const [isEditing, setIsEditing] = useState(false);
    const { data, setData, put, processing, errors, reset } = useForm({
        body: comment.body,
    });

    function submitEdit(e) {
        e.preventDefault();
        put(route('loot.items.comments.update', { item: itemId, comment: comment.id }), {
            preserveScroll: true,
            onSuccess: () => setIsEditing(false),
        });
    }

    function handleDelete() {
        if (confirm('Are you sure you want to delete this comment?')) {
            router.delete(route('loot.items.comments.destroy', { item: itemId, comment: comment.id }), {
                preserveScroll: true,
            });
        }
    }

    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-GB', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    }

    return (
        <div className="border border-gray-700 rounded-lg p-4 bg-brown-800">
            {/* Header with user info and timestamp */}
            <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-3">
                    <img
                        src={comment.user.avatar}
                        alt={comment.user.display_name}
                        className="w-8 h-8 rounded-full"
                    />
                    <div>
                        <span className="font-medium">{comment.user.display_name}</span>
                        {comment.user.highest_role && (
                            <span className={`ml-2 text-xs bg-discord${comment.user.highest_role ? '-' + comment.user.highest_role.toLowerCase() : ''} px-2 py-0.5 rounded`}>
                                {comment.user.highest_role}
                            </span>
                        )}
                    </div>
                </div>
                <span className="text-sm text-gray-400">
                    {formatDate(comment.created_at)}
                </span>
            </div>

            {/* Comment body or edit form */}
            {isEditing ? (
                <form onSubmit={submitEdit}>
                    <textarea
                        value={data.body}
                        onChange={e => setData('body', e.target.value)}
                        rows={4}
                        className="w-full rounded-md border-gray-600 bg-brown-900 text-white focus:border-amber-500 focus:ring-amber-500"
                    />
                    <InputError message={errors.body} className="mt-2" />
                    <div className="mt-3 flex gap-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="px-3 py-1 bg-amber-600 hover:bg-amber-700 rounded text-sm font-medium transition-colors"
                        >
                            {processing ? 'Saving...' : 'Save'}
                        </button>
                        <button
                            type="button"
                            onClick={() => { setIsEditing(false); reset(); }}
                            className="px-3 py-1 bg-gray-600 hover:bg-gray-700 rounded text-sm font-medium transition-colors"
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
                <div className="mt-3 pt-3 border-t border-gray-700 flex gap-4 text-sm">
                    {comment.can.edit && (
                        <button
                            onClick={() => setIsEditing(true)}
                            className="text-amber-400 hover:text-amber-300 transition-colors"
                        >
                            <i className="fas fa-edit mr-1"></i> Edit
                        </button>
                    )}
                    {comment.can.delete && (
                        <button
                            onClick={handleDelete}
                            className="text-red-400 hover:text-red-300 transition-colors"
                        >
                            <i className="fas fa-trash mr-1"></i> Delete
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
