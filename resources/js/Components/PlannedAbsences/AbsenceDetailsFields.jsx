import { Fieldset, Legend } from "@headlessui/react";
import { FieldError, FormRow, FormSection, RadioCardGroup, TextInput } from "@/Components/FormControls";
import MarkdownEditor from "@/Components/MarkdownEditor";
import todayDateString from "@/Helpers/todayDateString";

const ALLOWED_FORMATS = ["bold", "italic", "underline", "bulletList", "numberedList"];

/**
 * The start date, end date, reason and notes fields shared by the officer and
 * self-service absence forms. Expects the `data`, `setData`, `errors` and
 * `clearErrors` of an Inertia `useForm`. Pass `isOfficer` to word the hints
 * for someone logging an absence on another member's behalf.
 */
export default function AbsenceDetailsFields({
    data,
    setData,
    errors,
    clearErrors,
    reasons,
    canBackdate = false,
    isOfficer = false,
}) {
    const awaySubject = isOfficer ? "they'll" : "you'll";
    const isOtherReason = data.reason === "Other";

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
                <TextInput
                    id="start_date"
                    name="start_date"
                    type="date"
                    value={data.start_date}
                    onChange={(event) => setField("start_date", event.target.value)}
                    min={canBackdate ? undefined : todayDateString()}
                    max="2099-12-31"
                    invalid={Boolean(errors.start_date)}
                    required
                    className="scheme-dark"
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
                <TextInput
                    id="end_date"
                    name="end_date"
                    type="date"
                    value={data.end_date}
                    onChange={(event) => setField("end_date", event.target.value)}
                    min={data.start_date || undefined}
                    max="2099-12-31"
                    invalid={Boolean(errors.end_date)}
                    className="scheme-dark"
                />
            </FormRow>

            <Fieldset className="flex flex-col gap-1.5 md:col-span-2">
                <Legend className="text-gray-300 text-sm font-medium">
                    Reason
                    <span aria-hidden="true" className="ml-1 text-red-300">
                        *
                    </span>
                </Legend>
                <p className="text-gray-300 text-sm">
                    {isOfficer
                        ? "Pick the closest match."
                        : "Pick the closest match. You can add more detail in the notes."}
                </p>
                <RadioCardGroup
                    id="reason"
                    name="reason"
                    value={data.reason}
                    onChange={(value) => {
                        setField("reason", value);
                        clearErrors("notes");
                    }}
                    options={reasons.map((reason) => ({
                        value: reason.key,
                        label: reason.label,
                        description: reason.description,
                        icon: reason.icon,
                    }))}
                    invalid={Boolean(errors.reason)}
                    className="grid scroll-mt-4 grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                />
                <FieldError message={errors.reason} />
            </Fieldset>

            <FormRow
                htmlFor="notes"
                label="Notes"
                required={isOtherReason}
                hint={
                    isOtherReason
                        ? "Required for Other. A short note is enough, such as being at a concert."
                        : "Optional. Anything else officers should know."
                }
                error={errors.notes}
                className="md:col-span-2"
            >
                <MarkdownEditor
                    id="notes"
                    value={data.notes ?? ""}
                    onChange={(value) => {
                        setData("notes", value);
                        if (value.trim()) {
                            clearErrors("notes");
                        }
                    }}
                    allowedFormats={ALLOWED_FORMATS}
                    rows={4}
                />
            </FormRow>
        </FormSection>
    );
}
