import { useForm } from "@inertiajs/react";
import { useFormAutosave } from "@/Hooks/useAutosave";
import useSyncedSelection from "@/Hooks/useSyncedSelection";

/** How long after focus leaves a dataset form section its autosave runs. */
export const AUTOSAVE_DELAY = 750;

/**
 * The form behind a dataset relationship section: id-list fields that
 * autosave on the Edit page, or submit through handleSubmit on a setup
 * step, calling onSaved afterwards.
 *
 * `selected` maps each field to the ids the server last returned, and
 * `key` names the section in the page's autosave queue. setIds(field, ids)
 * updates a field and schedules its autosave.
 */
export default function useRelationshipForm({ url, key, selected, autosave = false, onSaved }) {
    const form = useForm(selected);
    useSyncedSelection(form, selected);

    const { schedule, containerProps } = useFormAutosave({
        form,
        url,
        saved: selected,
        key,
        trigger: "blur",
        delay: AUTOSAVE_DELAY,
        enabled: autosave,
    });

    function setIds(field, ids) {
        form.setData(field, ids);
        schedule();
    }

    function handleSubmit(e) {
        e.preventDefault();
        form.patch(url, {
            preserveScroll: true,
            onSuccess: () => onSaved?.(),
        });
    }

    return { form, setIds, handleSubmit, containerProps };
}
