import { useState, useCallback } from "react";
import MarkdownEditor from "@/Components/MarkdownEditor";
import Icon from "@/Components/FontAwesome/Icon";

const ALLOWED_FORMATS = ["bold", "italic", "bulletList", "numberedList", "wowheadLink"];
const VALIDATION_RULES = ["noUnderline", "noRegularLinks"];

/**
 * Presentational comment composer.
 *
 * This component does not know any routes. The owning CommentsSection passes
 * `onSubmit(body, { onSuccess, onError })` and decides which API endpoint the
 * body goes to. `isEdit` only changes the button labels.
 */
export default function CommentForm({
    onSubmit,
    isEdit = false,
    initialBody = "",
    resetOnSuccess = false,
    onCancel = null,
}) {
    const [body, setBody] = useState(initialBody);
    const [validationError, setValidationError] = useState(null);
    const [serverError, setServerError] = useState(null);
    const [processing, setProcessing] = useState(false);

    const handleValidationChange = useCallback((error) => {
        setValidationError(error);
    }, []);

    function submit(e) {
        e.preventDefault();

        if (validationError || processing) {
            return;
        }

        setServerError(null);
        setProcessing(true);

        onSubmit(body, {
            onSuccess: () => {
                setProcessing(false);
                if (resetOnSuccess) {
                    setBody("");
                    setValidationError(null);
                }
            },
            onError: (errors) => {
                setProcessing(false);
                setServerError(errors?.body ?? "Something went wrong. Please try again.");
            },
        });
    }

    return (
        <form onSubmit={submit}>
            <MarkdownEditor
                value={body}
                onChange={setBody}
                allowedFormats={ALLOWED_FORMATS}
                validationRules={VALIDATION_RULES}
                rows={4}
                error={serverError}
                onValidationChange={handleValidationChange}
                className="mb-2"
            />
            <div className="flex gap-2">
                <button
                    type="submit"
                    disabled={processing || validationError}
                    className={`inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-sm font-semibold tracking-wide text-white transition duration-150 ease-in-out hover:bg-amber-700 focus:bg-amber-700 focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:outline-hidden active:bg-amber-800 ${
                        (processing || validationError) && "opacity-25"
                    }`}
                >
                    <Icon icon="paper-plane" style="solid" className="mr-1" />
                    {processing ? (isEdit ? "Saving..." : "Posting...") : isEdit ? "Save Changes" : "Post Comment"}
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
