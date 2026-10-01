import { useEffect, useRef, useState } from "react";
import { Link } from "@inertiajs/react";
import { Button, Input } from "@headlessui/react";
import {
    ErrorSummary,
    OptionSelect,
    RequiredFieldsNote,
    SlugInput,
    buttonClassName,
    controlClassName,
    linkClassName,
} from "@/Components/FormControls";
import { useFormAutosave } from "@/Hooks/useAutosave";
import { AUTOSAVE_DELAY } from "@/Hooks/useRelationshipForm";

/**
 * A dataset's details form, either autosaving (`autosave`) or submitted
 * through `onSubmit`.
 *
 * `children` renders the fields, given text, slug and select helpers bound to
 * `form`.
 */
export default function DetailsForm({ form, autosave, onSubmit, submitLabel, processingLabel, cancelHref, children }) {
    const { data, setData, processing, errors } = form;
    const summaryRef = useRef(null);
    const [failedSubmits, setFailedSubmits] = useState(0);
    const { schedule, flush, containerProps } = useFormAutosave({
        form,
        url: autosave?.url,
        saved: autosave?.saved ?? {},
        key: "details",
        trigger: "blur",
        delay: AUTOSAVE_DELAY,
        enabled: Boolean(autosave),
    });

    // Focus after React commits the errors render, so the summary exists.
    useEffect(() => {
        if (failedSubmits > 0) {
            summaryRef.current?.focus();
        }
    }, [failedSubmits]);

    function handleSubmit(e) {
        e.preventDefault();

        // Pressing Enter saves straight away.
        if (autosave) {
            flush();
            return;
        }

        onSubmit({ onError: () => setFailedSubmits((count) => count + 1) });
    }

    const text = (name, props = {}) => (
        <Input
            id={name}
            name={name}
            value={data[name]}
            onChange={(e) => setData(name, e.target.value)}
            invalid={!!errors[name]}
            className={controlClassName}
            {...props}
        />
    );

    const slug = (name, props = {}) => (
        <SlugInput
            name={name}
            value={data[name]}
            onChange={(value) => setData(name, value)}
            invalid={!!errors[name]}
            {...props}
        />
    );

    const select = (name, props = {}) => (
        <OptionSelect
            name={name}
            value={data[name]}
            onChange={(value) => {
                setData(name, value);
                schedule();
            }}
            invalid={!!errors[name]}
            {...props}
        />
    );

    return (
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-8" {...containerProps}>
            <ErrorSummary errors={errors} summaryRef={summaryRef} />

            <RequiredFieldsNote />

            {children({ text, slug, select })}

            {!autosave && (
                <div className="flex flex-wrap items-center gap-4">
                    <Button type="submit" disabled={processing} className={buttonClassName}>
                        {processing ? processingLabel : submitLabel}
                    </Button>
                    <Link href={cancelHref} className={linkClassName}>
                        Cancel
                    </Link>
                </div>
            )}
        </form>
    );
}
