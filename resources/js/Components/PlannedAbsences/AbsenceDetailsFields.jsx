import { Input } from "@headlessui/react";
import { FormRow, FormSection, controlClassName } from "@/Components/FormControls";
import MarkdownEditor from "@/Components/MarkdownEditor";

const ALLOWED_FORMATS = ["bold", "italic", "underline", "bulletList", "numberedList"];

/**
 * The start date, end date and reason fields shared by the officer and
 * self-service absence forms. Expects the `data`, `setData`, `errors` and
 * `clearErrors` of an Inertia `useForm`. Pass `isOfficer` to word the hints
 * for someone logging an absence on another member's behalf.
 */
export default function AbsenceDetailsFields({ data, setData, errors, clearErrors, canBackdate = false, isOfficer = false }) {
    const awaySubject = isOfficer ? "they'll" : "you'll";

    function setField(field, value) {
        setData(field, value);
        clearErrors(field);
    }

    return (
        <FormSection id="the-absence" legend="The absence">
            <FormRow
                htmlFor="start_date"
                label="Start date"
                required
                hint={`The first day ${awaySubject} be away.`}
                error={errors.start_date}
            >
                <Input
                    id="start_date"
                    name="start_date"
                    type="date"
                    value={data.start_date}
                    onChange={(event) => setField("start_date", event.target.value)}
                    min={canBackdate ? undefined : new Date().toISOString().split("T")[0]}
                    max="2099-12-31"
                    invalid={Boolean(errors.start_date)}
                    required
                    className={`${controlClassName} scheme-dark`}
                />
            </FormRow>

            <FormRow
                htmlFor="end_date"
                label="End date"
                hint={
                    isOfficer
                        ? "The last day they'll be away. Leave it blank if it's not known yet, and it will show as ongoing until it's updated."
                        : "The last day you'll be away. If you're not sure, leave it blank and it will show as ongoing until it's updated."
                }
                error={errors.end_date}
            >
                <Input
                    id="end_date"
                    name="end_date"
                    type="date"
                    value={data.end_date}
                    onChange={(event) => setField("end_date", event.target.value)}
                    min={data.start_date || undefined}
                    max="2099-12-31"
                    invalid={Boolean(errors.end_date)}
                    className={`${controlClassName} scheme-dark`}
                />
            </FormRow>

            <FormRow htmlFor="reason" label="Reason" required error={errors.reason} className="md:col-span-2">
                <MarkdownEditor
                    id="reason"
                    value={data.reason}
                    onChange={(value) => {
                        setData("reason", value);
                        if (value.trim()) {
                            clearErrors("reason");
                        }
                    }}
                    allowedFormats={ALLOWED_FORMATS}
                    rows={5}
                />
            </FormRow>
        </FormSection>
    );
}
