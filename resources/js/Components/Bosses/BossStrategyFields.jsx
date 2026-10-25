import { startTransition, useEffect, useMemo, useOptimistic, useRef } from "react";
import { router, useForm, usePage } from "@inertiajs/react";
import Relationships from "@/Components/Datasets/Relationships";
import { firstError } from "@/Components/FormControls";
import ImageManager from "@/Components/ImageManager";
import MarkdownEditor from "@/Components/MarkdownEditor";
import {
    AUTOSAVE_HEADERS,
    autosaveVisit,
    enqueueOrRun,
    useAutosaveQueue,
    useAutosaveStatus,
    useFormAutosave,
} from "@/Hooks/useAutosave";
import { AUTOSAVE_DELAY } from "@/Hooks/useRelationshipForm";

/**
 * The boss's strategy on its Edit page: markdown notes that save shortly
 * after typing stops, and images that save as soon as they are uploaded,
 * removed or reordered. Images are sent by media id. Removals and reorders
 * show straight away, and the list returns to `boss.images` once they save or
 * fail. Without `canEdit`, the images can't be uploaded, removed or reordered.
 */
export default function BossStrategyFields({ boss, url, canEdit = true }) {
    const savedNotes = useMemo(() => ({ notes: boss.notes ?? "" }), [boss.notes]);
    const notesForm = useForm(savedNotes);
    const { schedule } = useFormAutosave({
        form: notesForm,
        url,
        saved: savedNotes,
        key: "strategy-notes",
        delay: AUTOSAVE_DELAY,
    });

    const queue = useAutosaveQueue();
    const status = useAutosaveStatus();
    const { errors } = usePage().props;
    const [images, addImageChange] = useOptimistic(boss.images, applyImageChange);
    const savedImages = useRef(boss.images);

    useEffect(() => {
        savedImages.current = boss.images;
    }, [boss.images]);

    const imageError =
        firstError(errors, "images") ?? firstError(errors, "removed_image_ids") ?? firstError(errors, "image_ids");

    /**
     * Queue an image save as `key`. `getData` builds the request data when the
     * save runs. Returns a Promise of the save's result.
     */
    function saveImages(key, getData, options = {}) {
        return enqueueOrRun(queue, key, () =>
            autosaveVisit(router, "patch", url, { data: getData(), headers: AUTOSAVE_HEADERS, ...options }),
        );
    }

    function handleUpload(files) {
        if (!canEdit) {
            return;
        }

        saveImages(`strategy-images-upload-${Date.now()}`, () => ({ images: files }), { forceFormData: true });
    }

    function handleDelete(id) {
        if (!canEdit) {
            return;
        }

        startTransition(async () => {
            addImageChange({ type: "remove", id });
            await saveImages(`strategy-images-remove-${id}`, () => ({ removed_image_ids: [id] }));
        });
    }

    function handleReorder(ids) {
        if (!canEdit) {
            return;
        }

        startTransition(async () => {
            addImageChange({ type: "reorder", ids });
            await saveImages("strategy-images-order", () => ({
                image_ids: applyImageChange(savedImages.current, { type: "reorder", ids }).map((image) => image.id),
            }));
        });
    }

    return (
        <Relationships
            id="strategy"
            title="Strategy"
            description="Members see these notes and images on the boss's strategy page and in raid plans."
        >
            <div className="flex flex-col gap-1.5">
                <label htmlFor="notes" className="text-sm font-medium text-gray-300">
                    Strategy notes
                </label>
                <MarkdownEditor
                    id="notes"
                    value={notesForm.data.notes}
                    onChange={(value) => {
                        notesForm.setData("notes", value);
                        schedule();
                    }}
                    rows={12}
                    error={notesForm.errors.notes}
                />
            </div>

            <div className="flex flex-col gap-1.5">
                <h3 className="text-sm font-medium text-gray-300">Strategy images</h3>
                <ImageManager
                    images={images}
                    label="strategy image"
                    saving={status?.status === "saving"}
                    disabled={!canEdit}
                    error={imageError}
                    onUpload={handleUpload}
                    onDelete={handleDelete}
                    onReorder={handleReorder}
                />
            </div>
        </Relationships>
    );
}

/**
 * Apply an image change to a list of images. `{ type: "remove", id }` drops
 * that image. `{ type: "reorder", ids }` puts the images in `ids` order,
 * skipping ids that aren't in the list, followed by any image not in `ids`
 * in its existing order.
 */
function applyImageChange(images, change) {
    if (change.type === "remove") {
        return images.filter((image) => image.id !== change.id);
    }

    const imagesById = new Map(images.map((image) => [image.id, image]));
    const ordered = change.ids.filter((id) => imagesById.has(id)).map((id) => imagesById.get(id));
    const unordered = images.filter((image) => !change.ids.includes(image.id));

    return [...ordered, ...unordered];
}
