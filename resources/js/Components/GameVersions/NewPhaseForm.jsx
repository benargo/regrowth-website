import { Input } from "@headlessui/react";
import { useForm } from "@inertiajs/react";
import { FormRow, SaveButton, controlClassName } from "@/Components/FormControls";
import { autosaveVisit, useAutosaveQueue } from "@/Hooks/useAutosave";

export default function NewPhaseForm({ gameVersion }) {
    const form = useForm({ number: "", description: "", start_date: "" });
    const error = (field) => form.errors[`new_phase.${field}`];

    const queue = useAutosaveQueue();

    /**
     * On an autosaving page, the add waits its turn in the save queue, so its
     * visit neither cancels nor is cancelled by an autosave. It is sent
     * without the autosave header, so the flash message still confirms it.
     */
    function handleSubmit(e) {
        e.preventDefault();
        form.transform((data) => ({ new_phase: data }));
        const url = route("management.game-versions.update", gameVersion.id);

        if (queue) {
            queue.enqueue("new-phase", () => autosaveVisit(form, "patch", url, { onSuccess: () => form.reset() }));
            return;
        }

        form.patch(url, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

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
