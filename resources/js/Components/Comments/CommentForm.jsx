import { useState, useCallback } from "react";
import MarkdownEditor from "@/Components/MarkdownEditor";
import Icon from "@/Components/FontAwesome/Icon";
import { PrimaryButton, SecondaryButton } from "@/Components/FormControls";

const ALLOWED_FORMATS = ["bold", "italic", "bulletList", "numberedList", "quote", "wowheadLink"];
const VALIDATION_RULES = ["noUnderline", "noRegularLinks"];

/**
 * Presentational comment composer.
 *
 * This component does not know any routes. The owning CommentsSection passes
 * `onSubmit(body, { onSuccess, onError })` and decides which API endpoint the
 * body goes to. `isEdit` only changes the button labels.
 */
function SubmitLabel({ isEdit, isReply, processing }) {
    if (processing) {
        if (isEdit) {
            return "Saving...";
        }
        if (isReply) {
            return "Replying...";
        }
        return "Posting...";
    }

    if (isEdit) {
        return "Save changes";
    }
    if (isReply) {
        return "Post reply";
    }
    return "Post comment";
}

function buildQuotePrefix(quotedComment) {
    if (!quotedComment) {
        return "";
    }

    const quotedLines = quotedComment.body
        .split("\n")
        .map((line) => `> ${line}`)
        .join("\n");

    return `> **${quotedComment.user.display_name}:**\n${quotedLines}\n\n`;
}

export default function CommentForm({
    onSubmit,
    isEdit = false,
    isReply = false,
    initialBody = "",
    resetOnSuccess = false,
    onCancel = null,
    quotedComment = null,
    autoFocus = false,
}) {
    const [body, setBody] = useState(initialBody || buildQuotePrefix(quotedComment));
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
                autoFocus={autoFocus}
            />
            <div className="flex gap-2">
                <PrimaryButton type="submit" processing={processing} disabled={!!validationError}>
                    <Icon icon="paper-plane" style="solid" />
                    <SubmitLabel isEdit={isEdit} isReply={isReply} processing={processing} />
                </PrimaryButton>
                {onCancel && <SecondaryButton onClick={onCancel}>Cancel</SecondaryButton>}
            </div>
        </form>
    );
}
