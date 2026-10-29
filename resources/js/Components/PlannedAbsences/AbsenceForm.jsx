import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import { ErrorSummary, PrimaryButton, RequiredFieldsNote, linkClassName } from "@/Components/FormControls";
import { useErrorSummaryFocus } from "@/Components/FormPage";

const END_BEFORE_START_MESSAGE = "The end date must be on or after the start date.";

/**
 * The absence form's sections, linked from the "On this page" sidebar. Each
 * `value` is the id of its FormSection.
 */
export const ABSENCE_SECTIONS = [
    { value: "whos-away", label: "Who's away" },
    { value: "the-absence", label: "The absence" },
];

/**
 * The frame of the officer and self-service absence forms: an error summary,
 * the required-fields note, the fields, then Save and Cancel. `data` and
 * `setError` come from the page's Inertia `useForm`, so the dates can be
 * checked before the request is sent. `onSubmit` receives Inertia visit
 * options to pass to `post` or `patch`.
 */
export default function AbsenceForm({ data, setError, errors, processing, onSubmit, submitLabel, cancelHref, children }) {
    const { summaryRef, focusSummary } = useErrorSummaryFocus();

    function handleSubmit(event) {
        event.preventDefault();

        // Dates are YYYY-MM-DD strings, so they compare in calendar order.
        if (data.start_date && data.end_date && data.end_date < data.start_date) {
            setError("end_date", END_BEFORE_START_MESSAGE);
            focusSummary();

            return;
        }

        onSubmit({ onError: focusSummary });
    }

    return (
        <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-8">
            <ErrorSummary errors={errors} summaryRef={summaryRef} />

            <RequiredFieldsNote />

            {children}

            <div className="flex flex-wrap items-center gap-4">
                <PrimaryButton type="submit" processing={processing}>
                    <Icon icon="calendar-plus" style="solid" />
                    {processing ? "Saving…" : submitLabel}
                </PrimaryButton>
                <Link href={cancelHref} className={linkClassName}>
                    Cancel
                </Link>
            </div>
        </form>
    );
}
