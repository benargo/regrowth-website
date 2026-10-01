import { useForm } from "@inertiajs/react";
import { autosaveVisit, useAutosaveQueue } from "@/Hooks/useAutosave";

/**
 * The form behind a dataset's "Add a …" panel. Its fields are sent nested
 * under `field` (e.g. { new_phase: { … } }) to the record's update URL, and
 * reset once saved. error(name) reads that field's nested validation error.
 *
 * On an autosaving page, the add waits its turn in the save queue (as `key`),
 * so its visit neither cancels nor is cancelled by an autosave. It is sent
 * without the autosave header, so the flash message still confirms it.
 */
export default function useNewRecordForm({ url, key, field, initial }) {
    const form = useForm(initial);
    const queue = useAutosaveQueue();

    const error = (name) => form.errors[`${field}.${name}`];

    function handleSubmit(e) {
        e.preventDefault();
        form.transform((data) => ({ [field]: data }));
        const onSuccess = () => form.reset();

        if (queue) {
            queue.enqueue(key, () => autosaveVisit(form, "patch", url, { onSuccess }));
            return;
        }

        form.patch(url, { preserveScroll: true, onSuccess });
    }

    return { form, error, handleSubmit };
}
