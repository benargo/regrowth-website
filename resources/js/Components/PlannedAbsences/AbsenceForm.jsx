import { useEffect, useRef, useState } from "react";
import { Link } from "@inertiajs/react";
import { Button } from "@headlessui/react";
import Icon from "@/Components/FontAwesome/Icon";
import { ErrorSummary, RequiredFieldsNote, buttonClassName, linkClassName } from "@/Components/FormControls";

/**
 * The frame of the officer and self-service absence forms: an error summary,
 * the required-fields note, the fields, then Save and Cancel. `onSubmit`
 * receives Inertia visit options to pass to `post` or `patch`.
 */
export default function AbsenceForm({ errors, processing, onSubmit, submitLabel, cancelHref, children }) {
    const summaryRef = useRef(null);
    const [failedSubmits, setFailedSubmits] = useState(0);

    // Focus after React commits the errors render, so the summary exists.
    useEffect(() => {
        if (failedSubmits > 0) {
            summaryRef.current?.focus();
        }
    }, [failedSubmits]);

    // The game version has no field of its own; it's chosen with the character.
    const { game_version: gameVersionError, ...summaryErrors } = errors;

    if (gameVersionError && !summaryErrors.character) {
        summaryErrors.character = gameVersionError;
    }

    function handleSubmit(event) {
        event.preventDefault();
        onSubmit({ onError: () => setFailedSubmits((count) => count + 1) });
    }

    return (
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-8">
            <ErrorSummary errors={summaryErrors} summaryRef={summaryRef} />

            <RequiredFieldsNote />

            {children}

            <div className="flex flex-wrap items-center gap-4">
                <Button type="submit" disabled={processing} className={buttonClassName}>
                    <Icon icon="calendar-plus" style="solid" />
                    {processing ? "Saving…" : submitLabel}
                </Button>
                <Link href={cancelHref} className={linkClassName}>
                    Cancel
                </Link>
            </div>
        </form>
    );
}
