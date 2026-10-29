import { Link } from "@inertiajs/react";
import {
    CheckboxInput,
    ErrorSummary,
    OptionSelect,
    PrimaryButton,
    RequiredFieldsNote,
    SlugInput,
    TextInput,
    linkClassName,
} from "@/Components/FormControls";
import { useErrorSummaryFocus } from "@/Components/FormPage";
import { useFormAutosave } from "@/Hooks/useAutosave";
import { AUTOSAVE_DELAY } from "@/Hooks/useRelationshipForm";

/**
 * A dataset's details form, either autosaving (`autosave`) or submitted
 * through `onSubmit`.
 *
 * `children` renders the fields, given text, slug, select and checkbox helpers
 * bound to `form`, clear(name), which empties a field and saves, and
 * schedule(), which saves the changed fields (for controls such as a colour
 * picker that set data themselves).
 */
export default function DetailsForm({ form, autosave, onSubmit, submitLabel, processingLabel, cancelHref, children }) {
    const { data, setData, processing, errors } = form;
    const { summaryRef, focusSummary } = useErrorSummaryFocus();
    const { schedule, flush, containerProps } = useFormAutosave({
        form,
        url: autosave?.url,
        saved: autosave?.saved ?? {},
        key: "details",
        trigger: "blur",
        delay: AUTOSAVE_DELAY,
        enabled: Boolean(autosave),
    });

    function handleSubmit(e) {
        e.preventDefault();

        // Pressing Enter saves straight away.
        if (autosave) {
            flush();
            return;
        }

        onSubmit({ onError: focusSummary });
    }

    const text = (name, props = {}) => (
        <TextInput
            id={name}
            name={name}
            value={data[name]}
            onChange={(e) => setData(name, e.target.value)}
            invalid={!!errors[name]}
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

    const checkbox = (name, props = {}) => (
        <CheckboxInput
            name={name}
            checked={data[name]}
            onChange={(checked) => {
                setData(name, checked);
                schedule();
            }}
            invalid={!!errors[name]}
            {...props}
        />
    );

    const clear = (name) => {
        setData(name, "");
        schedule();
    };

    return (
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-8" {...containerProps}>
            <ErrorSummary errors={errors} summaryRef={summaryRef} />

            <RequiredFieldsNote />

            {children({ text, slug, select, checkbox, clear, schedule })}

            {!autosave && (
                <div className="flex flex-wrap items-center gap-4">
                    <PrimaryButton type="submit" processing={processing}>
                        {processing ? processingLabel : submitLabel}
                    </PrimaryButton>
                    <Link href={cancelHref} className={linkClassName}>
                        Cancel
                    </Link>
                </div>
            )}
        </form>
    );
}
