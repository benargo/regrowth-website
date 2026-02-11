import { useState, useCallback } from "react";
import { useForm } from "@inertiajs/react";
import MarkdownEditor from "@/Components/MarkdownEditor";
import Icon from "@/Components/FontAwesome/Icon";

const ALLOWED_FORMATS = ["bold", "italic", "bulletList", "numberedList", "wowheadLink"];
const VALIDATION_RULES = ["noUnderline", "noRegularLinks"];

export default function CommentForm({ itemId, commentId = null, initialBody = "", onSuccess = null, onCancel = null }) {
    const [validationError, setValidationError] = useState(null);
    const { data, setData, post, put, processing, errors, reset } = useForm({
        body: initialBody,
    });

    const handleValidationChange = useCallback((error) => {
        setValidationError(error);
    }, []);

    function submit(e) {
        e.preventDefault();

        if (validationError) {
            return;
        }

        if (commentId) {
            put(route("loot.items.comments.update", { item: itemId, comment: commentId }), {
                preserveScroll: true,
                onSuccess: () => onSuccess?.(),
            });
        } else {
            post(route("loot.items.comments.store", { item: itemId }), {
                preserveScroll: true,
                onSuccess: () => {
                    reset("body");
                    setValidationError(null);
                    onSuccess?.();
                },
            });
        }
    }

    return (
        <form onSubmit={submit}>
            <MarkdownEditor
                value={data.body}
                onChange={(value) => setData("body", value)}
                allowedFormats={ALLOWED_FORMATS}
                validationRules={VALIDATION_RULES}
                rows={4}
                error={errors.body}
                onValidationChange={handleValidationChange}
                className="mb-1"
            />
            <div className="flex gap-2">
                <button
                    type="submit"
                    disabled={processing || validationError}
                    className={`inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-sm font-semibold tracking-wide text-white transition duration-150 ease-in-out hover:bg-amber-700 focus:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 active:bg-amber-800 ${
                        (processing || validationError) && "opacity-25"
                    }`}
                >
                    <Icon icon="paper-plane" style="solid" className="mr-1" />
                    {processing
                        ? commentId
                            ? "Saving..."
                            : "Posting..."
                        : commentId
                          ? "Save Changes"
                          : "Post Comment"}
                </button>
                {onCancel && (
                    <button
                        type="button"
                        onClick={onCancel}
                        className="inline-flex items-center rounded-md border border-gray-600 px-4 py-2 text-sm font-semibold tracking-wide text-white transition duration-150 ease-in-out hover:bg-gray-700"
                    >
                        Cancel
                    </button>
                )}
            </div>
        </form>
    );
}
