import { memo, useId, useState } from "react";
import { router } from "@inertiajs/react";
import { FormRow } from "@/Components/FormControls";
import ImageManager from "@/Components/ImageManager";
import { autosaveVisit, enqueueOrRun, useAutosaveQueue } from "@/Hooks/useAutosave";

/**
 * One image upload for a dataset record, showing the image at `url`. Files
 * are sent to `updateUrl` as soon as they are chosen, as `{ [field]: file }`
 * in a POST with a spoofed PATCH method; deleting sends `{ [field]: null }`.
 * `accepts` lists the MIME types the server takes.
 *
 * On an autosaving page, each upload or delete waits its turn in the save
 * queue. Memoised.
 */
const ImageUploadField = memo(function ImageUploadField({ field, label, hint, url, accepts, updateUrl }) {
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const queue = useAutosaveQueue();
    const inputId = useId();

    function send(method, data, options = {}) {
        enqueueOrRun(queue, `image-${field}`, () =>
            autosaveVisit(router, method, updateUrl, {
                data,
                ...options,
                onStart: () => setSaving(true),
                onSuccess: () => setError(null),
                onError: (errors) => setError(errors[field] ?? null),
                onFinish: () => setSaving(false),
            }),
        );
    }

    return (
        <FormRow htmlFor={inputId} label={label} hint={hint}>
            <ImageManager
                inputId={inputId}
                label={label.toLowerCase()}
                images={url ? [{ id: field, url }] : []}
                saving={saving}
                error={error}
                max={1}
                accepts={accepts}
                onUpload={([file]) => send("post", { _method: "patch", [field]: file }, { forceFormData: true })}
                onDelete={() => send("patch", { [field]: null })}
            />
        </FormRow>
    );
});

export default ImageUploadField;
