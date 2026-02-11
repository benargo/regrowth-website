import { useState, useEffect, useCallback } from "react";
import { useForm } from "@inertiajs/react";
import FormattedMarkdown from "@/Components/FormattedMarkdown";
import MarkdownEditor from "@/Components/MarkdownEditor";
import Icon from "@/Components/FontAwesome/Icon";

const ALLOWED_FORMATS = ["bold", "italic", "underline", "link", "wowheadLink"];
const VALIDATION_RULES = ["noLineBreaks"];

export default function Notes({ notes, itemId, canEdit }) {
    if (!notes && !canEdit) {
        return null;
    }

    const [validationError, setValidationError] = useState(null);
    const { data, setData, post, processing, errors, reset, setDefaults } = useForm({
        notes: notes || "",
    });

    // Sync form data when notes prop changes (e.g., after save and redirect)
    useEffect(() => {
        setDefaults({ notes: notes || "" });
        setData("notes", notes || "");
    }, [notes]);

    const handleValidationChange = useCallback((error) => {
        setValidationError(error);
    }, []);

    function submit(e) {
        e.preventDefault();
        if (validationError) {
            return;
        }
        post(route("loot.items.notes.store", { item: itemId }), {
            preserveScroll: true,
            onSuccess: () => {
                setValidationError(null);
            },
        });
    }

    if (canEdit) {
        return (
            <form onSubmit={submit} className="mt-8">
                <h2 className="mb-2 text-xl font-bold">Officers&rsquo; notes</h2>
                <p className="text-md mb-4 text-gray-400">
                    Notes are unique to each loot item. If you change what another officer has written, it will
                    overwrite their notes.
                </p>
                <MarkdownEditor
                    value={data.notes}
                    onChange={(value) => setData("notes", value)}
                    allowedFormats={ALLOWED_FORMATS}
                    validationRules={VALIDATION_RULES}
                    rows={2}
                    error={errors.notes}
                    onValidationChange={handleValidationChange}
                    className="mb-1"
                />
                <div className="mb-4 flex flex-col justify-between gap-4 md:flex-row">
                    {/* Actions */}
                    <div className="flex items-center justify-between gap-4 md:order-2">
                        <button
                            type="submit"
                            disabled={processing || validationError}
                            className={`inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-sm font-semibold tracking-wide text-white transition duration-150 ease-in-out hover:bg-amber-700 focus:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 active:bg-amber-800 ${
                                (processing || validationError) && "opacity-25"
                            }`}
                        >
                            <Icon icon="save" style="solid" className="mr-1" />{" "}
                            {processing ? "Saving..." : "Save notes"}
                        </button>
                        <button
                            type="button"
                            onClick={() => reset("notes")}
                            disabled={processing}
                            className={`inline-flex items-center rounded-md border border-transparent px-4 py-2 text-sm font-semibold tracking-wide text-white transition duration-150 ease-in-out hover:bg-brown-700 focus:bg-brown-700 focus:outline-none focus:ring-2 focus:ring-brown-500 focus:ring-offset-2 active:bg-brown-800 ${
                                processing && "opacity-25"
                            }`}
                        >
                            <Icon icon="trash" style="solid" className="mr-1" /> Reset notes
                        </button>
                    </div>
                </div>
            </form>
        );
    }

    return (
        <div className="mt-8">
            <h2 className="mb-6 text-xl font-bold">Officers&rsquo; Notes</h2>
            <FormattedMarkdown>{notes}</FormattedMarkdown>
        </div>
    );
}
