import { Fieldset, Input, Legend } from "@headlessui/react";
import { Description, Label } from "@/Components/Catalyst/fieldset";
import { Radio, RadioField, RadioGroup } from "@/Components/Catalyst/radio";
import Icon from "@/Components/FontAwesome/Icon";
import { FormRow, FormSection, controlClassName } from "@/Components/FormControls";
import MarkdownEditor from "@/Components/MarkdownEditor";

const ALLOWED_FORMATS = ["bold", "italic", "underline", "bulletList", "numberedList"];

/**
 * The start date, end date, reason and notes fields shared by the officer and
 * self-service absence forms. Expects the `data`, `setData`, `errors` and
 * `clearErrors` of an Inertia `useForm`. Pass `isOfficer` to word the hints
 * for someone logging an absence on another member's behalf.
 */
export default function AbsenceDetailsFields({ data, setData, errors, clearErrors, reasons, canBackdate = false, isOfficer = false }) {
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

            <Fieldset className="flex flex-col gap-1.5 md:col-span-2">
                <Legend className="text-secondary-300 text-sm font-medium">
                    Reason
                    <span aria-hidden="true" className="ml-1 text-red-300">
                        *
                    </span>
                </Legend>
                <p className="text-secondary-300 text-sm">
                    {isOfficer ? "Pick the closest match." : "Pick the closest match. You can add more detail in the notes."}
                </p>
                <RadioGroup
                    id="reason"
                    name="reason"
                    value={data.reason}
                    onChange={(value) => {
                        setField("reason", value);
                        clearErrors("notes");
                    }}
                    aria-invalid={errors.reason ? true : undefined}
                    className="grid scroll-mt-48 grid-cols-1 gap-3 space-y-0! sm:grid-cols-2 lg:scroll-mt-28"
                >
                    {reasons.map((reason) => (
                        <RadioField
                            key={reason.key}
                            className={
                                "border-ink-600 bg-ground-800 has-data-hover:border-ink-400 has-data-checked:border-ink-300 " +
                                "has-data-checked:bg-ink-600/30 rounded border p-3"
                            }
                        >
                            <Radio value={reason.key} color="sky" />
                            <Label className="flex items-center gap-2">
                                <Icon icon={reason.icon} style="solid" className="w-4 text-center text-sky-400" aria-hidden="true" />
                                {reason.label}
                            </Label>
                            <Description>{reason.description}</Description>
                        </RadioField>
                    ))}
                </RadioGroup>
                {errors.reason && <p className="text-sm text-red-300">{errors.reason}</p>}
            </Fieldset>

            <FormRow
                htmlFor="notes"
                label="Notes"
                required={isOtherReason}
                hint={
                    isOtherReason
                        ? "Required for Other. A short note is enough, such as which officer you told."
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
