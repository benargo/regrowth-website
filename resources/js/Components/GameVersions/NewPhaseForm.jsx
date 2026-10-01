import { Input } from "@headlessui/react";
import { FormRow, SaveButton, controlClassName } from "@/Components/FormControls";
import useNewRecordForm from "@/Hooks/useNewRecordForm";

export default function NewPhaseForm({ url }) {
    const { form, error, handleSubmit } = useNewRecordForm({
        url,
        key: "new-phase",
        field: "new_phase",
        initial: { number: "", description: "", start_date: "" },
    });

    return (
        <form onSubmit={handleSubmit} noValidate className="grid grid-cols-1 gap-4 md:grid-cols-4">
            <FormRow
                htmlFor="new-phase-number"
                label="Phase number"
                required
                hint="For example 1 or 2.5."
                error={error("number")}
            >
                <Input
                    id="new-phase-number"
                    type="number"
                    step="0.1"
                    min="0"
                    max="9.9"
                    inputMode="decimal"
                    required
                    value={form.data.number}
                    onChange={(e) => form.setData("number", e.target.value)}
                    invalid={!!error("number")}
                    className={controlClassName}
                />
            </FormRow>
            <FormRow
                htmlFor="new-phase-description"
                label="Description"
                required
                error={error("description")}
                className="md:col-span-2"
            >
                <Input
                    id="new-phase-description"
                    required
                    autoComplete="off"
                    value={form.data.description}
                    onChange={(e) => form.setData("description", e.target.value)}
                    invalid={!!error("description")}
                    className={controlClassName}
                />
            </FormRow>
            <FormRow htmlFor="new-phase-start-date" label="Start date" error={error("start_date")}>
                <Input
                    id="new-phase-start-date"
                    type="date"
                    value={form.data.start_date}
                    onChange={(e) => form.setData("start_date", e.target.value)}
                    invalid={!!error("start_date")}
                    className={controlClassName}
                />
            </FormRow>
            <div className="md:col-span-4">
                <SaveButton processing={form.processing} label="Add phase" processingLabel="Adding…" />
            </div>
        </form>
    );
}
