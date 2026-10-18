import { memo, useState } from "react";
import { router } from "@inertiajs/react";
import { FormRow } from "@/Components/FormControls";
import ImageManager from "@/Components/ImageManager";
import { autosaveVisit, useAutosaveQueue } from "@/Hooks/useAutosave";

/**
 * One image upload for a dataset record, showing the image at `url`. Files
 * are sent to `updateUrl` as soon as they are chosen, as `{ [field]: file }`
 * with the PATCH spoofed over POST because browsers can't send files in a
 * real PATCH; deleting sends `{ [field]: null }`. `accepts` lists the MIME
 * types the server takes.
 *
 * On an autosaving page, each upload or delete waits its turn in the save
 * queue, so its visit neither cancels nor is cancelled by an autosave.
 * Memoised, so dragging a colour picker beside it doesn't re-render it.
 */
const ImageUploadField = memo(function ImageUploadField({ field, label, hint, url, accepts, updateUrl }) {
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const queue = useAutosaveQueue();

    function send(method, data, options = {}) {
        const run = () =>
            autosaveVisit(router, method, updateUrl, {
                data,
                ...options,
                onStart: () => setSaving(true),
                onSuccess: () => setError(null),
                onError: (errors) => setError(errors[field] ?? null),
                onFinish: () => setSaving(false),
            });

        if (queue) {
            queue.enqueue(`image-${field}`, run);
            return;
        }

        run();
    }

    return (
        <FormRow label={label} hint={hint}>
            <ImageManager
                images={url ? [url] : []}
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
