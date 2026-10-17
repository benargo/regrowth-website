import DetailsForm from "@/Components/Datasets/DetailsForm";
import { FormRow, FormSection, linkClassName } from "@/Components/FormControls";
import { toServerDateTimeLocal } from "@/Helpers/serverTime";

/**
 * Build the initial useForm state for a phase, or for a new phase in the
 * given game version.
 */
export function phaseFormData(phase = null, gameVersionId = null) {
    return {
        number: phase?.number?.toString() ?? "",
        description: phase?.description ?? "",
        start_date: toServerDateTimeLocal(phase?.start_date),
        game_version_id: phase ? (phase.game_version?.id?.toString() ?? "") : (gameVersionId?.toString() ?? ""),
    };
}

/**
 * The phase details form, for creating or editing. Props other than
 * `options` pass through to DetailsForm.
 */
export default function PhaseForm({ form, options, ...detailsFormProps }) {
    const { errors } = form;

    return (
        <DetailsForm form={form} {...detailsFormProps}>
            {({ text, select, clear }) => (
                <>
                    <FormSection legend="The phase">
                        <FormRow
                            htmlFor="number"
                            label="Phase number"
                            required
                            hint="For example 1 or 3.5. It's shown as “Phase 3.5”."
                            error={errors.number}
                        >
                            {text("number", {
                                type: "number",
                                step: "0.1",
                                min: "0",
                                max: "9.9",
                                inputMode: "decimal",
                                required: true,
                            })}
                        </FormRow>
                        <FormRow
                            htmlFor="game_version_id"
                            label="Game version"
                            hint="Leave blank to link it to a game version later."
                            error={errors.game_version_id}
                        >
                            {select("game_version_id", {
                                options: options.game_versions,
                                placeholder: "No game version",
                            })}
                        </FormRow>
                        <FormRow
                            htmlFor="description"
                            label="Description"
                            required
                            error={errors.description}
                            className="md:col-span-2"
                        >
                            {text("description", { required: true, maxLength: 255, autoComplete: "off" })}
                        </FormRow>
                    </FormSection>

                    <FormSection legend="When it starts">
                        <FormRow
                            htmlFor="start_date"
                            label="Start date and time"
                            hint="In server time (Europe/Paris). Leave it blank until the date is announced."
                            error={errors.start_date}
                        >
                            {text("start_date", { type: "datetime-local", className: "scheme-dark" })}
                        </FormRow>
                        {form.data.start_date !== "" && (
                            <div className="flex items-end">
                                <button
                                    type="button"
                                    onClick={() => {
                                        document.getElementById("start_date")?.focus();
                                        clear("start_date");
                                    }}
                                    className={linkClassName}
                                >
                                    Clear start date
                                </button>
                            </div>
                        )}
                    </FormSection>
                </>
            )}
        </DetailsForm>
    );
}
